<?php

declare(strict_types=1);

namespace App\Shared\Transfer;

/**
 * How an import cell is read before validation.
 */
enum ImportColumnType: string
{
    case Text = 'text';
    case Date = 'date';
    /** Accepts the value or its label (case-insensitive) from ImportColumn::$options. */
    case Choice = 'choice';
    /** ISO alpha-2 country code, upper-cased. */
    case Country = 'country';
}
