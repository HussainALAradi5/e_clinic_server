<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Dtos\Shared\ChargeLine;
use App\Enums\Payments\AppointmentFeeStatus;
use App\Enums\Payments\AppointmentFeeType;
use App\Interfaces\Shared\Chargeable;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\Base\BaseModel;
use App\Models\Identity\User;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fee for a visit that did not happen (FEE-2026-000001): a no-show or a late cancellation by the patient. It is
 * charged as a line on the patient's draft invoice (price from the price list) and can be waived — with a reason —
 * while that invoice is still a draft. One fee per appointment.
 *
 * @property string $id
 * @property string $code
 * @property string $appointment_id
 * @property string $patient_id
 * @property AppointmentFeeType $type
 * @property AppointmentFeeStatus $status
 * @property string $billable_service_id
 * @property Money $amount
 * @property string $currency
 * @property ?string $invoice_id
 * @property ?string $waived_by
 * @property ?CarbonImmutable $waived_at
 * @property ?string $waiver_reason
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Appointment $appointment
 * @property-read Patient $patient
 * @property-read ?Invoice $invoice
 * @property-read ?User $waiver
 */
class AppointmentFee extends BaseModel implements Chargeable, OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'FEE';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('appointment_fees')
            ->sortable('created_at', 'code', 'amount', 'type', 'status')
            ->filterable('type', ['eq', 'in'], enum: AppointmentFeeType::class)
            ->filterable('status', ['eq', 'in'], enum: AppointmentFeeStatus::class)
            ->filterable('patient_id', ['eq'])
            ->column('code', 'Code')
            ->column('patient', 'Patient')
            ->column('type', 'Fee')
            ->column('invoice', 'Invoice')
            ->column('status', 'Status')
            ->column('amount', 'Amount')
            ->column('created_at', 'Date')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    public function isWaived(): bool
    {
        return $this->status === AppointmentFeeStatus::Waived;
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** One line of the fee's price-list item, naming the missed visit. */
    public function chargeLines(): array
    {
        $visit = $this->appointment;

        return [new ChargeLine(
            $this->billable_service_id,
            '1',
            __('accounting.fee_line', ['fee' => $this->type->label(), 'code' => $visit->code, 'date' => $visit->scheduled_date->format('d M Y')]),
        )];
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function waiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->patient_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('appointment_fees.patient_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => AppointmentFeeType::class,
            'status' => AppointmentFeeStatus::class,
            'amount' => MoneyCast::class.':currency',
            'waived_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
