<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * How the client edits a filter (resource schema). Mirrors the Angular FilterFieldType enum.
 */
enum FilterFieldType: string implements HasLabel
{
    use EnumHelpers;

    case Text = 'text';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case Enum = 'enum';
    case Relation = 'relation';
    /** A fixed list of named values from the server (country, language, file type …). */
    case Choice = 'choice';
}
