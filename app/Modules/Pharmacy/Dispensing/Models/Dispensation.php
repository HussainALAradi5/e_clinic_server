<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Models;

use App\Enums\Pharmacy\DispensationKind;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Payments\Invoices\Concerns\BilledOnInvoice;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Contracts\Chargeable;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Data\ChargeLine;
use App\Shared\Enums\GenericStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medicine leaving stock for a patient: handed over by the pharmacy (take_home, with or without a prescription) or
 * given during a session (administered). Billed through the single billing path; cost of goods sold is posted.
 * `completed`, or `cancelled` when returned to stock the same day.
 *
 * @property string $id
 * @property string $code
 * @property DispensationKind $kind
 * @property ?string $prescription_id
 * @property ?string $appointment_id
 * @property string $patient_id
 * @property string $dispensed_by
 * @property ?string $witness_id
 * @property GenericStatus $status
 * @property CarbonImmutable $dispensed_at
 * @property ?string $journal_entry_id
 * @property ?string $reversal_journal_entry_id
 * @property ?string $notes
 * @property ?string $cancellation_reason
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Prescription $prescription
 * @property-read ?Appointment $appointment
 * @property-read Patient $patient
 * @property-read Employee $dispenser
 * @property-read ?Employee $witness
 * @property-read Collection<int, DispensationItem> $items
 */
class Dispensation extends BaseModel implements Chargeable, OwnedByUser
{
    use BilledOnInvoice;
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'DSP';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('dispensing')
            ->sortable('code', 'kind', 'status', 'dispensed_at')
            ->filterable('kind', ['eq', 'in'], enum: DispensationKind::class, label: 'Given as')
            ->filterable('status', ['eq', 'in'], enum: GenericStatus::class)
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('dispensed_by', ['eq', 'in'], resource: 'employees', label: 'Dispensed by')
            ->filterable('prescription_id', ['eq', 'null'])
            ->filterable('appointment_id', ['eq', 'null'], hidden: true)
            ->filterable('code', ['eq', 'starts'])
            ->includes('patient.person', 'dispenser.person', 'prescription', 'items.medication')
            ->column('code', 'Code')
            ->column('dispensed_at', 'Time')
            ->column('patient', 'Patient')
            ->column('kind', 'Given as')
            ->column('items', 'Medicines')
            ->column('dispensed_by', 'Dispensed by')
            ->column('status', 'Status')
            ->dateFields('dispensed_at')
            ->defaultSort('-dispensed_at');
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** One line per medication at the price-list price (the same price as the snapshot on the items). */
    public function chargeLines(): array
    {
        return array_values($this->items()->with('medication')->get()
            ->groupBy('medication_id')
            ->map(function (Collection $lines): ChargeLine {
                /** @var DispensationItem $first */
                $first = $lines->first();
                $quantity = $lines->reduce(fn ($sum, DispensationItem $i) => $sum->plus(Qty::of($i->quantity)), Qty::zero());

                return new ChargeLine($first->medication->billable_service_id, (string) $quantity, $first->medication->displayName());
            })
            ->all());
    }

    public function isCancelled(): bool
    {
        return $this->status === GenericStatus::Cancelled;
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dispensed_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function witness(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'witness_id');
    }

    /** @return HasMany<DispensationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DispensationItem::class)->orderBy('created_at')->orderBy('id');
    }

    /** Patient (portal), whoever dispensed it, or the clinician of its session. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        $id = $user->getAuthIdentifier();

        return in_array($id, [$this->patient_id, $this->dispensed_by], true)
            || ($this->appointment_id !== null && Appointment::query()->whereKey($this->appointment_id)->where('provider_id', $id)->exists());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        $id = $user->getAuthIdentifier();

        return $query->where(fn (Builder $q) => $q->where('dispensations.patient_id', $id)
            ->orWhere('dispensations.dispensed_by', $id)
            ->orWhereIn('dispensations.appointment_id', Appointment::query()->select('id')->where('provider_id', $id)));
    }

    protected function casts(): array
    {
        return [
            'kind' => DispensationKind::class,
            'status' => GenericStatus::class,
            'dispensed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
