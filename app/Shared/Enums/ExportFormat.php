<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * List export formats. Mirrors the Angular ExportFormat enum.
 */
enum ExportFormat: string implements HasLabel
{
    use EnumHelpers;

    case Csv = 'csv';
    case Xlsx = 'xlsx';
}
