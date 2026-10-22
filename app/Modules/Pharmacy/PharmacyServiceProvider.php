<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy;

use App\Modules\Pharmacy\Dispensing\Contracts\DispensingServiceInterface;
use App\Modules\Pharmacy\Dispensing\Http\Resources\DispensationResource;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Dispensing\Services\DispensingService;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Http\Resources\MedicationBatchResource;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Inventory\Models\StockMovement;
use App\Modules\Pharmacy\Inventory\Services\InventoryService;
use App\Modules\Pharmacy\Medications\Contracts\MedicationServiceInterface;
use App\Modules\Pharmacy\Medications\Http\Resources\MedicationResource;
use App\Modules\Pharmacy\Medications\Imports\MedicationImport;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Medications\Services\MedicationService;
use App\Modules\Pharmacy\Prescriptions\Contracts\PrescriptionServiceInterface;
use App\Modules\Pharmacy\Prescriptions\Events\PrescriptionCreated;
use App\Modules\Pharmacy\Prescriptions\Listeners\NotifyPatientOfPrescription;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Pharmacy\Prescriptions\Services\PrescriptionService;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Pharmacy module: medication catalogue (priced through the service catalogue), prescriptions, dispensing (billed
 * through invoices, costed in the journal) and batch-level inventory.
 */
final class PharmacyServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MedicationServiceInterface::class => MedicationService::class,
        PrescriptionServiceInterface::class => PrescriptionService::class,
        DispensingServiceInterface::class => DispensingService::class,
        InventoryServiceInterface::class => InventoryService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen(PrescriptionCreated::class, NotifyPatientOfPrescription::class);

        $bulk->register('medications', Medication::class, Medication::listQueryConfig(), MedicationService::class)
            ->exportable(MedicationResource::class, fn (Builder $q) => Medication::addStock($q, app(ClinicClock::class)->today())->with(MedicationService::RELATIONS), values: [
                'name' => fn (Model $m): string => $m instanceof Medication ? $m->displayName() : '',
                'price' => fn (Model $m): string => $m instanceof Medication ? MoneyHelper::format($m->billableService->price) : '',
                'stock_on_hand' => fn (Model $m): string => $m instanceof Medication ? Qty::str($m->stock_on_hand) : '',
            ])
            ->importable(MedicationImport::class);

        $bulk->register('prescriptions', Prescription::class, Prescription::listQueryConfig(), genericActions: false);

        $bulk->register('dispensing', Dispensation::class, Dispensation::listQueryConfig(), genericActions: false)
            ->exportable(DispensationResource::class, fn (Builder $q) => $q->with(DispensingService::RELATIONS), values: [
                'patient' => fn (Model $d): string => $d instanceof Dispensation ? $d->patient->person->full_name : '',
                'dispensed_by' => fn (Model $d): string => $d instanceof Dispensation ? $d->dispenser->person->full_name : '',
                'items' => fn (Model $d): string => $d instanceof Dispensation ? $d->items->map(fn ($i) => $i->medication->displayName().' × '.Qty::str($i->quantity))->implode('; ') : '',
            ]);

        $bulk->register('inventory', MedicationBatch::class, MedicationBatch::listQueryConfig(), genericActions: false)
            ->exportable(MedicationBatchResource::class, fn (Builder $q) => $q->with(['medication', 'supplier']), values: [
                'medication' => fn (Model $b): string => $b instanceof MedicationBatch ? $b->medication->displayName() : '',
                'unit_cost' => fn (Model $b): string => $b instanceof MedicationBatch ? MoneyHelper::format($b->unit_cost) : '',
                'supplier' => fn (Model $b): string => $b instanceof MedicationBatch ? (string) $b->supplier?->name : '',
            ]);

        $bulk->register('stock_movements', StockMovement::class, StockMovement::listQueryConfig(), genericActions: false)
            ->readableBy('inventory.read_all|inventory.read_own')
            ->permissionsOf('inventory');
    }
}
