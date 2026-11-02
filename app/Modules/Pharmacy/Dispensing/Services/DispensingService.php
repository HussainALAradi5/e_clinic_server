<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Pharmacy\DispensationKind;
use App\Enums\Pharmacy\StockLocation;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Pharmacy\Dispensing\Contracts\DispensingServiceInterface;
use App\Modules\Pharmacy\Dispensing\Data\AdministerData;
use App\Modules\Pharmacy\Dispensing\Data\AppointmentMedicines;
use App\Modules\Pharmacy\Dispensing\Data\DispenseData;
use App\Modules\Pharmacy\Dispensing\Data\DispenseItemData;
use App\Modules\Pharmacy\Dispensing\Events\MedicationsDispensed;
use App\Modules\Pharmacy\Dispensing\Exceptions\AdministrationRequiresSessionException;
use App\Modules\Pharmacy\Dispensing\Exceptions\ControlledDrugRuleException;
use App\Modules\Pharmacy\Dispensing\Exceptions\DispenseQuantityExceededException;
use App\Modules\Pharmacy\Dispensing\Exceptions\MedicationRequiresPrescriptionException;
use App\Modules\Pharmacy\Dispensing\Exceptions\PrescriptionNotDispensableException;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Dispensing\Models\DispensationItem;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Pharmacy\Prescriptions\Models\PrescriptionItem;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DispensingService implements DispensingServiceInterface
{
    public const RELATIONS = ['patient.person', 'dispenser.person', 'prescription', 'items.medication'];

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly InventoryServiceInterface $inventory,
        private readonly InvoiceServiceInterface $invoices,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): Dispensation
    {
        return $this->query()->with(['witness.person', 'appointment', 'items.batch', 'invoiceLine.invoice'])->findOrFail($id);
    }

    public function dispense(DispenseData $data): Dispensation
    {
        $dispensation = DB::transaction(function () use ($data): Dispensation {
            $medications = $this->medications($data->items);
            $prescription = null;
            if ($data->prescriptionId !== null) {
                $prescription = Prescription::query()->lockForUpdate()->find($data->prescriptionId)
                    ?? throw ValidationException::withMessages(['prescription_id' => [__('validation.exists', ['attribute' => 'prescription'])]]);
                if ($prescription->patient_id !== $data->patientId) {
                    throw ValidationException::withMessages(['prescription_id' => [__('validation.in', ['attribute' => 'prescription'])]]);
                }
                if (! $prescription->isDispensable($this->clock->today())) {
                    throw new PrescriptionNotDispensableException(context: ['status' => $prescription->status->value, 'valid_until' => $prescription->valid_until->toDateString()]);
                }
                $items = $this->matchPrescription($prescription, $data->items, $medications);
            } else {
                $items = $data->items;
                foreach ($items as $item) {
                    $medication = $medications[$item->medicationId];
                    if ($medication->requires_prescription) {
                        throw new MedicationRequiresPrescriptionException(__('errors.MEDICATION_REQUIRES_PRESCRIPTION', ['medication' => $medication->displayName()]), ['medication_id' => $medication->id]);
                    }
                }
            }

            return $this->record(DispensationKind::TakeHome, $data->patientId, $items, $medications, $data->witnessId, $data->notes, $prescription);
        });
        MedicationsDispensed::dispatch($dispensation);

        return $this->find($dispensation->id);
    }

    public function administer(Appointment $appointment, AdministerData $data): Dispensation
    {
        $dispensation = DB::transaction(function () use ($appointment, $data): Dispensation {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($appointment->status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress], true)) {
                throw new AdministrationRequiresSessionException(context: ['status' => $appointment->status->value]);
            }

            return $this->record(DispensationKind::Administered, $appointment->patient_id, $data->items, $this->medications($data->items), $data->witnessId, $data->notes, appointment: $appointment, from: $data->from);
        });
        MedicationsDispensed::dispatch($dispensation);

        return $this->find($dispensation->id);
    }

    public function forAppointment(Appointment $appointment): AppointmentMedicines
    {
        $prescriptions = Prescription::query()->where('appointment_id', $appointment->id)
            ->with(['doctor.person', 'items' => fn ($q) => $q->withDispensed()->with('medication')])
            ->latest()->get();
        $dispensations = Dispensation::query()
            ->where(fn (Builder $q) => $q->where('appointment_id', $appointment->id)->orWhereIn('prescription_id', $prescriptions->modelKeys()))
            ->with(['dispenser.person', 'items.medication', 'items.batch'])
            ->latest('dispensed_at')->get();

        return new AppointmentMedicines($prescriptions, $dispensations);
    }

    public function cancel(Dispensation $dispensation, string $reason): Dispensation
    {
        DB::transaction(function () use ($dispensation, $reason): void {
            $dispensation = Dispensation::query()->with('items.batch')->lockForUpdate()->findOrFail($dispensation->id);
            $sameDay = $this->clock->toClinic($dispensation->dispensed_at)->isSameDay($this->clock->now());
            if ($dispensation->status !== GenericStatus::Completed || ! $sameDay) {
                throw new InvalidStateTransitionException(context: ['from' => $dispensation->status->value, 'to' => GenericStatus::Cancelled->value, 'reason' => 'same_day_only']);
            }

            $this->invoices->uncharge($dispensation);
            foreach ($dispensation->items as $item) {
                $this->inventory->restock($item->batch, Qty::of($item->quantity), $dispensation, $reason);
            }
            if ($dispensation->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($dispensation->journal_entry_id);
                $dispensation->reversal_journal_entry_id = $this->journal->reverse($entry, $this->clock->today(), $reason, fromSource: true)->id;
            }
            $dispensation->forceFill(['status' => GenericStatus::Cancelled, 'cancellation_reason' => $reason])->save();

            if ($dispensation->prescription_id !== null) {
                Prescription::query()->lockForUpdate()->findOrFail($dispensation->prescription_id)->syncDispensedStatus();
            }
        });

        return $this->find($dispensation->id);
    }

    /**
     * @return Builder<Dispensation>
     */
    private function query(): Builder
    {
        return OwnershipScope::apply(Dispensation::query()->with(self::RELATIONS), 'dispensing', Auth::user());
    }

    /**
     * Shared flow of both kinds: controlled-drug rule, FEFO issue per medicine from one place (take-home: the
     * pharmacy; given in a session: the treatment room unless the pharmacy is chosen), lines with price / cost snapshots,
     * prescription status, invoice charge and the cost-of-goods-sold entry.
     *
     * @param  list<DispenseItemData>  $items
     * @param  array<string, Medication>  $medications
     */
    private function record(
        DispensationKind $kind,
        string $patientId,
        array $items,
        array $medications,
        ?string $witnessId,
        ?string $notes,
        ?Prescription $prescription = null,
        ?Appointment $appointment = null,
        StockLocation $from = StockLocation::Pharmacy,
    ): Dispensation {
        $dispenser = $this->actingEmployee();
        $this->assertControlledRule($kind, $items, $medications, $dispenser, $witnessId, $prescription);

        $dispensation = Dispensation::query()->create([
            'kind' => $kind,
            'prescription_id' => $prescription?->id,
            'appointment_id' => $appointment?->id,
            'patient_id' => $patientId,
            'dispensed_by' => $dispenser,
            'witness_id' => $witnessId,
            'status' => GenericStatus::Completed,
            'dispensed_at' => $this->clock->now(),
            'notes' => $notes,
        ]);

        $cost = MoneyHelper::zero();
        foreach ($items as $item) {
            $medication = $medications[$item->medicationId];
            foreach ($this->inventory->issue($medication, $item->quantity, $item->batchId, $dispensation, $from) as $issue) {
                DispensationItem::query()->create([
                    'dispensation_id' => $dispensation->id,
                    'prescription_item_id' => $item->prescriptionItemId,
                    'medication_id' => $medication->id,
                    'batch_id' => $issue->batch->id,
                    'quantity' => (string) $issue->quantity,
                    'unit_price' => $medication->billableService->price,
                    'unit_cost' => $issue->batch->unit_cost,
                    'route' => $item->route,
                    'dose' => $item->dose,
                ]);
                $cost = $cost->plus($issue->batch->unit_cost->multipliedBy($issue->quantity, RoundingMode::HALF_UP));
            }
        }

        $prescription?->syncDispensedStatus();
        $this->invoices->charge($dispensation);
        if ($cost->isPositive()) {
            $dispensation->journal_entry_id = $this->postCost($dispensation, $cost)->id;
            $dispensation->save();
        }

        return $dispensation;
    }

    /**
     * Fills each line from the prescription (by line id or medicine); never more than what remains.
     *
     * @param  list<DispenseItemData>  $items
     * @param  array<string, Medication>  $medications
     * @return list<DispenseItemData>
     */
    private function matchPrescription(Prescription $prescription, array $items, array $medications): array
    {
        /** @var Collection<int, PrescriptionItem> $lines */
        $lines = $prescription->items()->withDispensed()->get();
        $matched = [];
        foreach ($items as $i => $item) {
            $line = $item->prescriptionItemId !== null
                ? $lines->firstWhere('id', $item->prescriptionItemId)
                : $lines->firstWhere('medication_id', $item->medicationId);
            if (! $line instanceof PrescriptionItem || $line->medication_id !== $item->medicationId) {
                throw ValidationException::withMessages(["items.{$i}.medication_id" => [__('validation.in', ['attribute' => 'medication'])]]);
            }
            $remaining = $line->remaining();
            if ($item->quantity->isGreaterThan($remaining)) {
                $name = $medications[$item->medicationId]->displayName();
                throw new DispenseQuantityExceededException(__('errors.DISPENSE_QUANTITY_EXCEEDED', ['remaining' => (string) $remaining, 'medication' => $name]), [
                    'remaining' => (string) $remaining,
                    'errors' => ["items.{$i}.quantity" => [__('errors.DISPENSE_QUANTITY_EXCEEDED', ['remaining' => (string) $remaining, 'medication' => $name])]],
                ]);
            }
            $matched[] = new DispenseItemData($item->medicationId, $item->quantity, $line->id, $item->batchId, $item->route, $item->dose);
        }

        return $matched;
    }

    /**
     * @param  list<DispenseItemData>  $items
     * @return array<string, Medication>
     */
    private function medications(array $items): array
    {
        $ids = array_map(fn (DispenseItemData $i): string => $i->medicationId, $items);
        $medications = Medication::query()->with('billableService')->whereIn('id', $ids)->get()->keyBy('id')->all();
        foreach ($ids as $i => $id) {
            if (! isset($medications[$id])) {
                throw ValidationException::withMessages(["items.{$i}.medication_id" => [__('validation.exists', ['attribute' => 'medication'])]]);
            }
        }

        return $medications;
    }

    /**
     * Controlled drugs: a second staff member who may dispense / administer witnesses it; taking it home needs a
     * prescription.
     *
     * @param  list<DispenseItemData>  $items
     * @param  array<string, Medication>  $medications
     */
    private function assertControlledRule(DispensationKind $kind, array $items, array $medications, string $dispenser, ?string $witnessId, ?Prescription $prescription): void
    {
        foreach ($items as $item) {
            $medication = $medications[$item->medicationId];
            if (! $medication->is_controlled) {
                continue;
            }
            $right = $kind === DispensationKind::Administered ? 'dispensing.administer' : 'dispensing.dispense';
            $witness = $witnessId === null ? null : User::query()->find($witnessId);
            $witnessed = $witness instanceof User && $witness->id !== $dispenser && $witness->can($right);
            if (! $witnessed || ($kind === DispensationKind::TakeHome && $prescription === null)) {
                throw new ControlledDrugRuleException(__('errors.CONTROLLED_DRUG_RULE', ['medication' => $medication->displayName()]), ['medication_id' => $medication->id]);
            }
        }
    }

    /** The pharmacist / clinician on record must have an employee file. */
    private function actingEmployee(): string
    {
        $id = Auth::id();
        if (! is_string($id) || ! Employee::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['dispensed_by' => [__('validation.exists', ['attribute' => 'employee'])]]);
        }

        return $id;
    }

    /** Dr cost of goods sold / Cr pharmacy inventory at the lots' cost. */
    private function postCost(Dispensation $dispensation, Money $cost): JournalEntry
    {
        $dispensation->loadMissing('patient.person');

        return $this->journal->postAutomatic(new AutomaticEntryData($dispensation, $this->clock->today(), __('accounting.medicines_dispensed', ['code' => $dispensation->code, 'patient' => $dispensation->patient->person->full_name]), [
            JournalLineData::debit(AccountMappingKey::CostOfGoodsSold, $cost, null, $dispensation->patient_id),
            JournalLineData::credit(AccountMappingKey::PharmacyInventory, $cost, null, $dispensation->patient_id),
        ]));
    }
}
