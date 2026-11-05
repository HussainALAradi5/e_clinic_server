<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Dtos\Shared\ChargeLine;
use App\Enums\Payments\PatientPackageStatus;
use App\Interfaces\Shared\Chargeable;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Appointments\Patient;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A package a patient bought (PPK-2026-000001): a copy of the catalog terms at the time of sale, how many
 * sessions are used, and its validity once paid. It is sold as one line on the patient's invoice.
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property string $service_package_id
 * @property string $covered_service_id
 * @property ?string $invoice_id
 * @property string $name
 * @property int $sessions_total
 * @property int $sessions_used
 * @property Money $price
 * @property string $currency
 * @property PatientPackageStatus $status
 * @property ?CarbonImmutable $starts_on
 * @property ?CarbonImmutable $expires_on
 * @property ?string $idempotency_key
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read ServicePackage $package
 * @property-read BillableService $coveredService
 * @property-read ?Invoice $invoice
 */
class PatientPackage extends BaseModel implements Chargeable, OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'PPK';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('patient_packages')
            ->sortable('created_at', 'code', 'expires_on', 'status')
            ->filterable('status', ['eq', 'in'], enum: PatientPackageStatus::class)
            ->filterable('patient_id', ['eq'])
            ->column('code', 'Code')
            ->column('patient', 'Patient')
            ->column('name', 'Package')
            ->column('sessions', 'Sessions')
            ->column('expires_on', 'Valid until')
            ->column('status', 'Status')
            ->dateFields('created_at', 'expires_on')
            ->defaultSort('-created_at');
    }

    public function sessionsLeft(): int
    {
        return max(0, $this->sessions_total - $this->sessions_used);
    }

    /** Value of one session at the time of sale; the last takes the rounding remainder. */
    public function sessionValue(int $sessionNumber): Money
    {
        $each = $this->price->dividedBy($this->sessions_total, RoundingMode::DOWN);

        return $sessionNumber < $this->sessions_total ? $each : $this->price->minus($each->multipliedBy($this->sessions_total - 1));
    }

    /** What the unused sessions are worth. */
    public function remainingValue(): Money
    {
        $value = $this->price->multipliedBy(0);
        for ($n = $this->sessions_used + 1; $n <= $this->sessions_total; $n++) {
            $value = $value->plus($this->sessionValue($n));
        }

        return $value;
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** The package's own price-list item, named after the package. */
    public function chargeLines(): array
    {
        return [new ChargeLine($this->package->sale_service_id, '1', $this->name.' — '.$this->code)];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<ServicePackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id')->withDeleted();
    }

    /** @return BelongsTo<BillableService, $this> */
    public function coveredService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class, 'covered_service_id')->withDeleted();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<PackageSession, $this> */
    public function sessionsTaken(): HasMany
    {
        return $this->hasMany(PackageSession::class)->orderBy('created_at');
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
        return $query->where('patient_packages.patient_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'status' => PatientPackageStatus::class,
            'price' => MoneyCast::class.':currency',
            'sessions_total' => 'integer',
            'sessions_used' => 'integer',
            'starts_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
