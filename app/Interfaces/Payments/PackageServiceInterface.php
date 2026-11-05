<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\ServicePackageData;
use App\Models\Appointments\Patient;
use App\Models\Payments\PatientPackage;
use App\Models\Payments\ServicePackage;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The package catalog and selling packages. Rules: a package covers one service, 2–100 sessions, valid 7–730
 * days; each gets its own price-list item posting to 2510 Unearned package revenue; only packages on sale can be
 * sold; a sale is a line on the patient's draft invoice and the package waits for that invoice to be paid.
 */
interface PackageServiceInterface
{
    /** @return LengthAwarePaginator<int, ServicePackage>|CursorPaginator<int, ServicePackage> */
    public function listCatalog(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findPackage(string $id): ServicePackage;

    public function create(ServicePackageData $data): ServicePackage;

    /** New terms apply to future sales; packages already sold keep theirs. */
    public function update(ServicePackage $package, ServicePackageData $data): ServicePackage;

    /** @return LengthAwarePaginator<int, PatientPackage>|CursorPaginator<int, PatientPackage> */
    public function listSold(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findSold(string $id): PatientPackage;

    public function sell(Patient $patient, ServicePackage $package, string $idempotencyKey): PatientPackage;
}
