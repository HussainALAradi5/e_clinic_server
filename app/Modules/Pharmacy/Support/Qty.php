<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Stock quantities are numeric(10,2): exact decimal arithmetic, never floats (SQLite sums come back as numbers).
 */
final class Qty
{
    public static function of(mixed $value): BigDecimal
    {
        $string = is_float($value) ? number_format($value, 2, '.', '') : (string) ($value ?? '0');

        return BigDecimal::of($string === '' ? '0' : $string)->toScale(2, RoundingMode::HALF_UP);
    }

    /** `12.50` — the API shape of every quantity. */
    public static function str(mixed $value): string
    {
        return (string) self::of($value);
    }

    public static function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2);
    }
}
