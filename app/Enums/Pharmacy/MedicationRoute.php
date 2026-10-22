<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * How a medicine is given.
 */
enum MedicationRoute: string implements HasLabel
{
    use EnumHelpers;

    case Oral = 'oral';
    case Topical = 'topical';
    case Intravenous = 'intravenous';
    case Intramuscular = 'intramuscular';
    case Subcutaneous = 'subcutaneous';
    case Inhalation = 'inhalation';
    case Ophthalmic = 'ophthalmic';
    case Otic = 'otic';
    case Nasal = 'nasal';
    case Rectal = 'rectal';
}
