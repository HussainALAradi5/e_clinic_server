<?php

declare(strict_types=1);

namespace App\Interfaces\Accounting;

use App\Dtos\Accounting\AccountData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountMapping;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AccountServiceInterface
{
    /**
     * Root accounts with their children loaded recursively.
     *
     * @return Collection<int, Account>
     */
    public function tree(?AccountType $type = null): Collection;

    /**
     * @return LengthAwarePaginator<int, Account>|CursorPaginator<int, Account>
     */
    public function listAccounts(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Account with parent and children. */
    public function findAccount(string $id): Account;

    public function findWithDeletedAccount(string $id): Account;

    /**
     * Records referencing the account (config accounting.account_usages), e.g. `['billable_services' => 3]`.
     *
     * @return array<string, int>
     */
    public function usage(Account $account): array;

    public function createAccount(AccountData $data): Account;

    /** Type / group flag cannot change once the account is used. */
    public function updateAccount(Account $account, AccountData $data): Account;

    /** Soft delete of an unused, non-system account without children. */
    public function deleteAccount(Account $account): void;

    public function archiveAccount(Account $account): Account;

    public function unarchiveAccount(Account $account): Account;

    public function restoreAccount(Account $account): Account;

    /**
     * Every mapping key with its account (null = not mapped yet).
     *
     * @return Collection<string, ?AccountMapping>
     */
    public function getMappings(): Collection;

    /**
     * @param  array<string, string>  $mappings  key => account id
     * @return Collection<string, ?AccountMapping>
     */
    public function updateMappings(array $mappings): Collection;

    /** Account used by automatic postings for a logical role (cached until the mappings change). */
    public function resolve(AccountMappingKey $key): Account;
}
