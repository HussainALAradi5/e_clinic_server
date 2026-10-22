<?php

declare(strict_types=1);

namespace App\Modules\Payments\Catalog\Services;

use App\Enums\Accounting\AccountType;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Payments\Catalog\Contracts\BillableServiceServiceInterface;
use App\Modules\Payments\Catalog\Data\BillableServiceData;
use App\Modules\Payments\Catalog\Exceptions\ServiceCodeTakenException;
use App\Modules\Payments\Catalog\Exceptions\ServiceInUseException;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<BillableService>
 */
final class BillableServiceService extends BaseEntityService implements BillableServiceServiceInterface
{
    public function __construct(ApplyListQuery $listQuery)
    {
        parent::__construct($listQuery);
    }

    public function listServices(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findService(string $id): BillableService
    {
        return $this->find($id);
    }

    public function findWithDeletedService(string $id): BillableService
    {
        return $this->findWithDeleted($id);
    }

    public function usage(BillableService $service): array
    {
        $counts = [];
        foreach (Config::array('payments.service_usages') as $key => $columns) {
            /** @var array{table: string, columns: list<string>} $columns */
            if (Schema::hasTable($columns['table'])) {
                $counts[(string) $key] = DB::table($columns['table'])
                    ->where(fn ($q) => array_map(fn (string $c) => $q->orWhere($c, $service->id), $columns['columns']))
                    ->count();
            }
        }

        return $counts;
    }

    public function createService(BillableServiceData $data): BillableService
    {
        return DB::transaction(function () use ($data): BillableService {
            $code = (string) $data->values['code'];
            if (GenericEntityScopes::withDeleted(BillableService::query())->where('code', $code)->exists()) {
                throw new ServiceCodeTakenException;
            }
            $this->assertRevenueAccount((string) $data->values['revenue_account_id']);

            $service = BillableService::query()->create($data->values);

            return $this->findService($service->id);
        });
    }

    public function updateService(BillableService $service, BillableServiceData $data): BillableService
    {
        return DB::transaction(function () use ($service, $data): BillableService {
            $service->fill($data->values);
            if ($service->isDirty('code') && BillableService::query()->withDeleted()->where('code', $service->code)->whereKeyNot($service->id)->exists()) {
                throw new ServiceCodeTakenException;
            }
            if ($service->isDirty('revenue_account_id')) {
                $this->assertRevenueAccount($service->revenue_account_id);
            }
            $service->save();

            return $this->findService($service->id);
        });
    }

    public function deleteService(BillableService $service): void
    {
        $this->delete($service);
    }

    public function archiveService(BillableService $service): BillableService
    {
        return $service->isArchived() ? $service : $this->archive($service);
    }

    public function unarchiveService(BillableService $service): BillableService
    {
        return $service->isArchived() ? $this->unarchive($service) : $service;
    }

    public function restoreService(BillableService $service): BillableService
    {
        return $service->isDeleted() ? $this->restore($service) : $service;
    }

    public function restore(Model $model): Model
    {
        /** @var BillableService $model */
        if (BillableService::query()->where('code', $model->code)->whereKeyNot($model->id)->exists()) {
            throw new ServiceCodeTakenException;
        }

        return parent::restore($model);
    }

    /**
     * @return Builder<BillableService>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(BillableService::query()->with(['specialty', 'revenueAccount']), 'services', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var BillableService $model */
        $usage = array_filter($this->usage($model));
        if ($usage !== []) {
            throw new ServiceInUseException(context: ['usage' => $usage]);
        }
    }

    private function assertRevenueAccount(string $accountId): void
    {
        $account = Account::query()->find($accountId);
        if (! $account instanceof Account || ! $account->isPostable() || $account->type !== AccountType::Revenue) {
            throw ValidationException::withMessages(['revenue_account_id' => [__('errors.ACCOUNT_NOT_POSTABLE')]]);
        }
    }
}
