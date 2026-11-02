<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Dtos\Accounting\AccountData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Exceptions\Accounting\AccountCodeTakenException;
use App\Exceptions\Accounting\AccountHasTransactionsException;
use App\Exceptions\Accounting\GroupAccountNotPostableException;
use App\Exceptions\Accounting\InvalidAccountParentException;
use App\Exceptions\Accounting\SystemAccountProtectedException;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountMapping;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\CacheKeys;
use App\Utilities\Shared\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @extends BaseEntityService<Account>
 */
final class AccountService extends BaseEntityService implements AccountServiceInterface
{
    public function __construct(ApplyListQuery $listQuery)
    {
        parent::__construct($listQuery);
    }

    public function tree(?AccountType $type = null): Collection
    {
        $accounts = Account::query()->when($type !== null, fn (Builder $q) => $q->where('type', $type))->orderBy('code')->get();
        $byParent = $accounts->groupBy(fn (Account $a): string => $a->parent_id ?? '');
        $attach = function (Account $account) use (&$attach, $byParent): Account {
            return $account->setRelation('children', ($byParent[$account->id] ?? collect())->map($attach)->values());
        };

        // Roots = no parent, or a parent filtered out by type.
        $ids = $accounts->pluck('id')->flip();

        return $accounts->filter(fn (Account $a): bool => $a->parent_id === null || ! $ids->has($a->parent_id))->map($attach)->values();
    }

    public function listAccounts(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findAccount(string $id): Account
    {
        return $this->find($id)->load(['children' => fn ($q) => $q->orderBy('code')]);
    }

    public function findWithDeletedAccount(string $id): Account
    {
        return $this->findWithDeleted($id);
    }

    public function usage(Account $account): array
    {
        $counts = [];
        foreach (Config::array('accounting.account_usages') as $key => $usage) {
            /** @var array{table: string, column: string} $usage */
            if (Schema::hasTable($usage['table'])) {
                $counts[(string) $key] = DB::table($usage['table'])->where($usage['column'], $account->id)->count();
            }
        }
        $counts['child_accounts'] = Account::query()->where('parent_id', $account->id)->count();

        return $counts;
    }

    public function createAccount(AccountData $data): Account
    {
        return DB::transaction(function () use ($data): Account {
            $code = (string) $data->values['code'];
            if (GenericEntityScopes::withDeleted(Account::query())->where('code', $code)->exists()) {
                throw new AccountCodeTakenException;
            }

            $account = new Account($data->values);
            $account->is_group = (bool) ($data->values['is_group'] ?? false);
            $account->normal_balance = $account->type->normalBalance();
            $this->placeUnder($account, $data->values['parent_id'] ?? null);
            $account->save();

            return $this->findAccount($account->id);
        });
    }

    public function updateAccount(Account $account, AccountData $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            $values = $data->values;
            unset($values['code']);
            $account->fill($values);

            if ($account->isDirty(['type', 'is_group']) && $this->isUsed($account)) {
                throw new AccountHasTransactionsException;
            }
            if ($account->isDirty('type')) {
                $account->normal_balance = $account->type->normalBalance();
            }
            if ($data->has('parent_id') || $account->isDirty('type')) {
                $this->placeUnder($account, $account->parent_id);
            }
            $account->save();
            $this->refreshLevels($account);

            return $this->findAccount($account->id);
        });
    }

    public function deleteAccount(Account $account): void
    {
        $this->delete($account);
    }

    public function archiveAccount(Account $account): Account
    {
        return $account->isArchived() ? $account : $this->archive($account);
    }

    public function unarchiveAccount(Account $account): Account
    {
        return $account->isArchived() ? $this->unarchive($account) : $account;
    }

    public function restoreAccount(Account $account): Account
    {
        return $account->isDeleted() ? $this->restore($account) : $account;
    }

    /** Also used by bulk archive: system / mapped accounts stay active. */
    public function archive(Model $model): Model
    {
        /** @var Account $model */
        $this->assertNotProtected($model);

        return parent::archive($model);
    }

    public function restore(Model $model): Model
    {
        /** @var Account $model */
        if (Account::query()->where('code', $model->code)->whereKeyNot($model->id)->exists()) {
            throw new AccountCodeTakenException;
        }

        return parent::restore($model);
    }

    public function getMappings(): Collection
    {
        $mapped = AccountMapping::query()->with('account')->get()->keyBy(fn (AccountMapping $m): string => $m->key->value);

        return collect(AccountMappingKey::cases())->mapWithKeys(fn (AccountMappingKey $key): array => [$key->value => $mapped[$key->value] ?? null]);
    }

    public function updateMappings(array $mappings): Collection
    {
        DB::transaction(function () use ($mappings): void {
            foreach ($mappings as $key => $accountId) {
                $mappingKey = AccountMappingKey::from($key);
                $account = Account::query()->find($accountId);
                if (! $account instanceof Account || ! $account->isPostable() || $account->type !== $mappingKey->accountType()) {
                    throw new GroupAccountNotPostableException(context: ['errors' => ["mappings.{$key}" => [__('errors.ACCOUNT_NOT_POSTABLE')]]]);
                }
                AccountMapping::query()->updateOrCreate(['key' => $mappingKey], ['account_id' => $account->id]);
            }
        });
        Cache::forget(CacheKeys::accountMappings());

        return $this->getMappings();
    }

    public function resolve(AccountMappingKey $key): Account
    {
        /** @var array<string, string> $ids */
        $ids = Cache::rememberForever(CacheKeys::accountMappings(), fn (): array => AccountMapping::query()->pluck('account_id', 'key')->all());

        return Account::query()->findOrFail($ids[$key->value] ?? null);
    }

    /**
     * @return Builder<Account>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Account::query()->with('parent'), 'accounts', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Account $model */
        $this->assertNotProtected($model);
        if ($this->isUsed($model) || Account::query()->where('parent_id', $model->id)->exists()) {
            throw new AccountHasTransactionsException(context: ['usage' => array_filter($this->usage($model))]);
        }
    }

    private function assertNotProtected(Account $account): void
    {
        if ($account->is_system || AccountMapping::query()->where('account_id', $account->id)->exists()) {
            throw new SystemAccountProtectedException;
        }
    }

    private function isUsed(Account $account): bool
    {
        return array_filter(array_diff_key($this->usage($account), ['child_accounts' => 0])) !== [];
    }

    /** Parent must be a group of the same type, never the account itself or one of its descendants. */
    private function placeUnder(Account $account, ?string $parentId): void
    {
        if ($parentId === null) {
            $account->parent_id = null;
            $account->level = 1;

            return;
        }

        $parent = Account::query()->find($parentId);
        $invalid = ! $parent instanceof Account || ! $parent->is_group || $parent->type !== $account->type
            || ($account->exists && in_array($parent->id, [$account->id, ...$this->descendantIds($account)], true));
        if ($invalid) {
            throw new InvalidAccountParentException(context: ['errors' => ['parent_id' => [__('errors.ACCOUNT_INVALID_PARENT')]]]);
        }

        $account->parent_id = $parent->id;
        $account->level = $parent->level + 1;
    }

    /** @return list<string> */
    private function descendantIds(Account $account): array
    {
        $ids = [];
        $frontier = [$account->id];
        while ($frontier !== []) {
            $frontier = Account::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
            $ids = [...$ids, ...$frontier];
        }

        return array_values($ids);
    }

    /** Keeps `level` of the moved account's subtree in step. */
    private function refreshLevels(Account $account): void
    {
        foreach (Account::query()->where('parent_id', $account->id)->get() as $child) {
            if ($child->level !== $account->level + 1) {
                $child->level = $account->level + 1;
                $child->save();
            }
            $this->refreshLevels($child);
        }
    }
}
