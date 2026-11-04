<?php

declare(strict_types=1);

namespace App\Enums\Documents;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Colour of a badge, highlight tile or notice on a generated PDF (paper is printed on the server, so its colours
 * live here).
 */
enum DocumentTone: string implements HasLabel
{
    use EnumHelpers;

    case Neutral = 'neutral';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Violet = 'violet';
    case Rose = 'rose';

    /** Text colour (hex). */
    public function ink(): string
    {
        return match ($this) {
            self::Neutral => '#334155',
            self::Info => '#1d4ed8',
            self::Success => '#047857',
            self::Warning => '#b45309',
            self::Danger => '#b91c1c',
            self::Violet => '#6d28d9',
            self::Rose => '#be185d',
        };
    }

    /** Soft background colour (hex). */
    public function wash(): string
    {
        return match ($this) {
            self::Neutral => '#f1f5f9',
            self::Info => '#eff6ff',
            self::Success => '#ecfdf5',
            self::Warning => '#fffbeb',
            self::Danger => '#fef2f2',
            self::Violet => '#f5f3ff',
            self::Rose => '#fdf2f8',
        };
    }
}
