<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Dosage form of a medication.
 */
enum MedicationForm: string implements HasLabel
{
    use EnumHelpers;

    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Syrup = 'syrup';
    case Suspension = 'suspension';
    case Injection = 'injection';
    case Cream = 'cream';
    case Ointment = 'ointment';
    case Drops = 'drops';
    case Inhaler = 'inhaler';
    case Suppository = 'suppository';
    case Other = 'other';
}
