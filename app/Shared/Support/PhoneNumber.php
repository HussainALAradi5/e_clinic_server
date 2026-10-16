<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Propaganistas\LaravelPhone\PhoneNumber as LibPhoneNumber;
use Throwable;

/**
 * Phone numbers in E.164 (default region BH) via propaganistas/laravel-phone.
 */
final class PhoneNumber
{
    public const DEFAULT_REGION = 'BH';

    /** E.164 form, e.g. `+97336001234`; the trimmed input when it cannot be parsed (validation reports it). */
    public static function normalise(string $raw, string $defaultRegion = self::DEFAULT_REGION): string
    {
        try {
            return (new LibPhoneNumber($raw, $defaultRegion))->formatE164();
        } catch (Throwable) {
            return trim($raw);
        }
    }

    public static function isValid(string $raw, string $defaultRegion = self::DEFAULT_REGION): bool
    {
        try {
            return (new LibPhoneNumber($raw, $defaultRegion))->isValid();
        } catch (Throwable) {
            return false;
        }
    }

    /** `+973****1234` for logs and audit. */
    public static function mask(string $e164): string
    {
        $visible = 4;
        $prefix = str_starts_with($e164, '+') ? substr($e164, 0, 4) : '';
        $rest = substr($e164, strlen($prefix));

        return $prefix.str_repeat('*', max(strlen($rest) - $visible, 0)).substr($rest, -$visible);
    }
}
