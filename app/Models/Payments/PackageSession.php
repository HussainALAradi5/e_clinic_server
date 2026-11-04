<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Models\Appointments\Appointment;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One visit covered by a patient's package: its share of the package price, moved from unearned to earned
 * revenue by its journal entry. One per appointment.
 *
 * @property string $id
 * @property string $patient_package_id
 * @property string $appointment_id
 * @property Money $value
 * @property ?string $journal_entry_id
 * @property ?CarbonImmutable $created_at
 * @property-read PatientPackage $package
 * @property-read Appointment $appointment
 */
class PackageSession extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['patient_package_id', 'appointment_id', 'value', 'journal_entry_id'];

    /** @return BelongsTo<PatientPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    protected function casts(): array
    {
        return [
            'value' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
