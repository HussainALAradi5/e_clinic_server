<?php

declare(strict_types=1);

namespace App\Interfaces\Reference;

use App\Dtos\Reference\CompanyData;
use App\Models\Reference\Company;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Company>|CursorPaginator<int, Company>
     */
    public function listCompanies(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Company with address and country (`usage()` gives the counts). */
    public function findCompany(string $id): Company;

    public function findWithDeletedCompany(string $id): Company;

    /**
     * Records referencing the company, per registered usage (config reference.company_usages).
     *
     * @return array<string, int>
     */
    public function usage(Company $company): array;

    /** Company and its address in one transaction. */
    public function createCompany(CompanyData $data): Company;

    public function updateCompany(Company $company, CompanyData $data): Company;

    /** Soft delete; a referenced company → CompanyInUseException (archive instead). */
    public function deleteCompany(Company $company): void;

    public function archiveCompany(Company $company): Company;

    public function unarchiveCompany(Company $company): Company;

    public function restoreCompany(Company $company): Company;
}
