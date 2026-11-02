<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Interfaces\Shared\Auditable;
use App\Models\HR\Employee;
use App\Traits\Shared\Auditable as AuditsChanges;
use App\Traits\Shared\Blameable;
use App\Traits\Shared\HasVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notes and vitals of one visit (same id as the appointment). SOAP text is PHI (encrypted); signing locks it and
 * corrections become addenda. BMI is computed, never stored.
 *
 * @property string $id
 * @property ?string $chief_complaint
 * @property ?string $history
 * @property ?string $examination
 * @property ?string $assessment
 * @property ?string $plan
 * @property ?string $temperature_c
 * @property ?int $pulse_bpm
 * @property ?int $systolic_bp
 * @property ?int $diastolic_bp
 * @property ?int $respiratory_rate
 * @property ?int $spo2_percent
 * @property ?string $weight_kg
 * @property ?string $height_cm
 * @property ?string $signed_by
 * @property ?CarbonImmutable $signed_at
 * @property int $version
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Appointment $appointment
 * @property-read ?Employee $signer
 * @property-read Collection<int, RecordDiagnosis> $diagnoses
 * @property-read Collection<int, RecordAddendum> $addenda
 */
class MedicalRecord extends Model implements Auditable
{
    use AuditsChanges;
    use Blameable;
    use HasVersion;

    public const NOTES = ['chief_complaint', 'history', 'examination', 'assessment', 'plan'];

    public const VITALS = ['temperature_c', 'pulse_bpm', 'systolic_bp', 'diastolic_bp', 'respiratory_rate', 'spo2_percent', 'weight_kg', 'height_cm'];

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $guarded = ['signed_by', 'signed_at', 'created_by', 'updated_by'];

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /** kg / m², one decimal; null without both measures. */
    public function bmi(): ?float
    {
        $weight = (float) $this->weight_kg;
        $height = (float) $this->height_cm / 100;

        return $weight > 0 && $height > 0 ? round($weight / ($height * $height), 1) : null;
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'signed_by');
    }

    /** @return HasMany<RecordDiagnosis, $this> */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(RecordDiagnosis::class)->orderByDesc('is_primary');
    }

    /** @return HasMany<RecordAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(RecordAddendum::class)->orderBy('created_at');
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return self::NOTES;
    }

    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::NOTES, 'encrypted'),
            'temperature_c' => 'decimal:1',
            'pulse_bpm' => 'integer',
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'respiratory_rate' => 'integer',
            'spo2_percent' => 'integer',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'signed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
