<?php

declare(strict_types=1);

namespace App\Utilities\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use TypeError;

/**
 * Thin wrapper around brick/money with clinic defaults (BHD, 3 decimals, HALF_UP). Floats are rejected by type.
 */
final class MoneyHelper
{
    public static function defaultCurrency(): string
    {
        return Config::string('clinic.currency');
    }

    /**
     * @throws TypeError for floats (even in non-strict callers)
     */
    public static function of(string|int|float|BigDecimal $amount, ?string $currency = null): Money
    {
        if (is_float($amount)) {
            throw new TypeError('Money amounts must be strings or integers, never floats.');
        }

        return Money::of($amount, $currency ?? self::defaultCurrency(), roundingMode: RoundingMode::HALF_UP);
    }

    public static function zero(?string $currency = null): Money
    {
        return Money::zero($currency ?? self::defaultCurrency());
    }

    /**
     * Sum of amounts in one currency; zero (default currency) when empty.
     */
    public static function sum(Money ...$amounts): Money
    {
        return $amounts === [] ? self::zero() : Money::total(...$amounts);
    }

    /**
     * Percentage of an amount, e.g. percentage(25.500 BHD, '10') = 2.550 BHD.
     */
    public static function percentage(Money $amount, string $percent): Money
    {
        return $amount->multipliedBy(BigDecimal::of($percent)->dividedBy(100, 6), RoundingMode::HALF_UP);
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public static function toApi(Money $money): array
    {
        return ['amount' => self::toDb($money), 'currency' => $money->getCurrency()->getCurrencyCode()];
    }

    /** Fixed 3-decimal string for numeric(14,3) columns, e.g. '25.500'. */
    public static function toDb(Money $money): string
    {
        return (string) $money->getAmount()->toScale(3, RoundingMode::HALF_UP);
    }

    /** Display string, e.g. 'BHD 25.500'. */
    public static function format(Money $money): string
    {
        return $money->getCurrency()->getCurrencyCode().' '.self::toDb($money);
    }
}
