<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Billable service category.
 */
enum ServiceCategory: string implements HasLabel
{
    use EnumHelpers;

    case Consultation = 'consultation';
    case Procedure = 'procedure';
    case Laboratory = 'laboratory';
    case Radiology = 'radiology';
    case Vaccination = 'vaccination';
    case Pharmacy = 'pharmacy';
    case Other = 'other';
}
