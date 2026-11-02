<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy;

use App\Events\Pharmacy\PrescriptionCreated;
use App\Http\Resources\Pharmacy\DispensationResource;
use App\Http\Resources\Pharmacy\MedicationBatchResource;
use App\Http\Resources\Pharmacy\MedicationResource;
use App\Imports\Pharmacy\MedicationImport;
use App\Interfaces\Pharmacy\DispensingServiceInterface;
use App\Interfaces\Pharmacy\InventoryServiceInterface;
use App\Interfaces\Pharmacy\MedicationServiceInterface;
use App\Interfaces\Pharmacy\PrescriptionServiceInterface;
use App\Listeners\Pharmacy\NotifyPatientOfPrescription;
use App\Models\Pharmacy\Dispensation;
use App\Models\Pharmacy\Medication;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\Prescription;
use App\Models\Pharmacy\StockMovement;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Pharmacy\DispensingService;
use App\Services\Pharmacy\InventoryService;
use App\Services\Pharmacy\MedicationService;
use App\Services\Pharmacy\PrescriptionService;
use App\Utilities\Pharmacy\Qty;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
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
