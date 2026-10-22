<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * List export formats (Excel, PDF, CSV). Mirrors the Angular ExportFormat enum.
 */
enum ExportFormat: string implements HasLabel
{
    use EnumHelpers;

    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }
}
