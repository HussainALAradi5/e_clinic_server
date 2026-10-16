<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of external organisation.
 */
enum CompanyType: string implements HasLabel
{
    use EnumHelpers;

    case Manufacturer = 'manufacturer';
    case Supplier = 'supplier';
    case Insurer = 'insurer';
    case Laboratory = 'laboratory';
    case Hospital = 'hospital';
    case Other = 'other';
}
