<?php

declare(strict_types=1);

use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

if (! function_exists('money')) {
    /** Shortcut for MoneyHelper::of(). */
    function money(string|int $amount, ?string $currency = null): Money
    {
        return MoneyHelper::of($amount, $currency);
    }
}

if (! function_exists('format_money')) {
    /** 'BHD 25.500'. */
    function format_money(Money $money): string
    {
        return MoneyHelper::format($money);
    }
}

if (! function_exists('clinic_now')) {
    /** Current instant (UTC) from the injectable ClinicClock. */
    function clinic_now(): CarbonImmutable
    {
        return app(ClinicClock::class)->now();
    }
}

if (! function_exists('clinic_today')) {
    /** Clinic-local date (Asia/Bahrain). */
    function clinic_today(): CarbonImmutable
    {
        return app(ClinicClock::class)->today();
    }
}

if (! function_exists('mask')) {
    /** '****4567' for logs and audit. */
    function mask(string $value, int $visible = 4): string
    {
        $length = mb_strlen($value);

        return $length <= $visible ? str_repeat('*', $length) : str_repeat('*', $length - $visible).mb_substr($value, -$visible);
    }
}
