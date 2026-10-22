<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Data;

use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use Illuminate\Support\Collection;

/**
 * Medicines of one appointment / session: what was prescribed there and what was given or handed over.
 */
final readonly class AppointmentMedicines
{
    /**
     * @param  Collection<int, Prescription>  $prescriptions
     * @param  Collection<int, Dispensation>  $dispensations
     */
    public function __construct(public Collection $prescriptions, public Collection $dispensations) {}
}
