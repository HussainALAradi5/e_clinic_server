<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Payments\ServicePackageData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\PatientPackageStatus;
use App\Enums\Payments\ServiceCategory;
use App\Exceptions\Payments\PackageNotOnSaleException;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\PackageServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Payments\BillableService;
use App\Models\Payments\PatientPackage;
use App\Models\Payments\ServicePackage;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PackageService implements PackageServiceInterface
{
    public const SOLD_RELATIONS = ['patient.person', 'package', 'coveredService', 'invoice'];

    public function __construct(
        private readonly InvoiceServiceInterface $invoices,
        private readonly AccountServiceInterface $accounts,
        private readonly ApplyListQuery $listQuery,
    ) {}

    public function listCatalog(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate(ServicePackage::query()->with('coveredService'), $query);
    }

    public function findPackage(string $id): ServicePackage
    {
        return ServicePackage::query()->with('coveredService')->findOrFail($id);
    }

    public function create(ServicePackageData $data): ServicePackage
    {
        return DB::transaction(function () use ($data): ServicePackage {
            // The package's own price-list item: selling it posts to unearned revenue until sessions are used.
            $sale = BillableService::query()->create([
                'code' => 'PKG-'.Str::upper(Str::random(8)),
                'name' => $data->name,
                'category' => ServiceCategory::Other,
                'price' => $data->price,
                'tax_rate' => '0',
                'is_insurable' => false,
                'revenue_account_id' => $this->accounts->resolve(AccountMappingKey::UnearnedPackages)->id,
            ]);
            $package = ServicePackage::query()->create([...$this->attributes($data), 'sale_service_id' => $sale->id]);

            return $this->findPackage($package->id);
        });
    }

    public function update(ServicePackage $package, ServicePackageData $data): ServicePackage
    {
        return DB::transaction(function () use ($package, $data): ServicePackage {
            $package->update($this->attributes($data));
            $package->saleService->update(['name' => $data->name, 'price' => $data->price, 'is_archived' => ! $data->isActive]);

            return $this->findPackage($package->id);
        });
    }

    public function listSold(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->sold(), $query);
    }

    public function findSold(string $id): PatientPackage
    {
        return $this->sold()->findOrFail($id);
    }

    public function sell(Patient $patient, ServicePackage $package, string $idempotencyKey): PatientPackage
    {
        $previous = PatientPackage::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($previous !== null) {
            return $this->findSold($previous->id);
        }
        if (! $package->is_active) {
            throw new PackageNotOnSaleException;
        }

        return DB::transaction(function () use ($patient, $package, $idempotencyKey): PatientPackage {
            $sold = PatientPackage::query()->create([
                'patient_id' => $patient->id,
                'service_package_id' => $package->id,
                'covered_service_id' => $package->covered_service_id,
                'name' => $package->name,
                'sessions_total' => $package->sessions,
                'price' => $package->price,
                'currency' => MoneyHelper::defaultCurrency(),
                'status' => PatientPackageStatus::AwaitingPayment,
                'idempotency_key' => $idempotencyKey,
            ]);
            $invoice = $this->invoices->charge($sold);
            $sold->update(['invoice_id' => $invoice?->id]);

            return $this->findSold($sold->id);
        });
    }

    /** @return array<string, mixed> */
    private function attributes(ServicePackageData $data): array
    {
        return [
            'name' => $data->name,
            'description' => $data->description,
            'covered_service_id' => $data->coveredServiceId,
            'sessions' => $data->sessions,
            'price' => $data->price,
            'validity_days' => $data->validityDays,
            'is_active' => $data->isActive,
        ];
    }

    /** @return Builder<PatientPackage> */
    private function sold(): Builder
    {
        return OwnershipScope::apply(PatientPackage::query()->with(self::SOLD_RELATIONS), 'patient_packages', Auth::user());
    }
}
