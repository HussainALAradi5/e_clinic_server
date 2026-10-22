<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Http\Controllers;

use App\Enums\Accounting\AccountType;
use App\Modules\Accounting\Accounts\Contracts\AccountServiceInterface;
use App\Modules\Accounting\Accounts\Http\Requests\AccountRequest;
use App\Modules\Accounting\Accounts\Http\Requests\UpdateMappingsRequest;
use App\Modules\Accounting\Accounts\Http\Resources\AccountMappingResource;
use App\Modules\Accounting\Accounts\Http\Resources\AccountResource;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Accounts\Models\AccountMapping;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

final class AccountController
{
    use ApiResponse;

    public function __construct(private readonly AccountServiceInterface $accounts) {}

    public function tree(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', Rule::enum(AccountType::class)]]);
        $type = $request->filled('type') ? AccountType::from($request->string('type')->toString()) : null;

        return $this->ok(AccountResource::collection($this->accounts->tree($type)));
    }

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Account::listQueryConfig());

        return $this->paginated(AccountResource::collection($this->accounts->listAccounts($query)), $query);
    }

    public function show(string $account): JsonResponse
    {
        $model = $this->accounts->findAccount($account);

        return $this->ok((new AccountResource($model))->withUsage($this->accounts->usage($model)));
    }

    public function store(AccountRequest $request): JsonResponse
    {
        return $this->created(new AccountResource($this->accounts->createAccount($request->toData())));
    }

    public function update(AccountRequest $request, string $account): JsonResponse
    {
        return $this->ok(new AccountResource($this->accounts->updateAccount($this->accounts->findAccount($account), $request->toData())));
    }

    public function destroy(string $account): Response
    {
        $this->accounts->deleteAccount($this->accounts->findAccount($account));

        return $this->noContent();
    }

    public function archive(string $account): JsonResponse
    {
        return $this->ok(new AccountResource($this->accounts->archiveAccount($this->accounts->findAccount($account))));
    }

    public function unarchive(string $account): JsonResponse
    {
        return $this->ok(new AccountResource($this->accounts->unarchiveAccount($this->accounts->findAccount($account))));
    }

    public function restore(string $account): JsonResponse
    {
        return $this->ok(new AccountResource($this->accounts->restoreAccount($this->accounts->findWithDeletedAccount($account))));
    }

    public function mappings(): JsonResponse
    {
        return $this->ok($this->mappingRows($this->accounts->getMappings()));
    }

    public function updateMappings(UpdateMappingsRequest $request): JsonResponse
    {
        return $this->ok($this->mappingRows($this->accounts->updateMappings($request->mappings())));
    }

    /**
     * @param  Collection<string, ?AccountMapping>  $mappings
     * @return list<array<string, mixed>>
     */
    private function mappingRows(Collection $mappings): array
    {
        return array_values($mappings->map(fn (?AccountMapping $m, string $key): array => AccountMappingResource::make($key, $m))->all());
    }
}
