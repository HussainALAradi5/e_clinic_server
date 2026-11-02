<?php

declare(strict_types=1);

namespace App\Utilities\Shared;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Searchable HMAC hashes for encrypted fields (CPR, passport): exact lookups and uniqueness
 * without ever putting the plain value in a WHERE clause.
 */
final class BlindIndex
{
    public static function make(string $value, string $context): string
    {
        return hash_hmac('sha256', self::normalise($value), self::key().$context);
    }

    /** Trim, upper-case, remove spaces and dashes. */
    public static function normalise(string $value): string
    {
        return Str::upper((string) preg_replace('/[\s\-]+/', '', $value));
    }

    private static function key(): string
    {
        $key = Config::string('clinic.blind_index_key', '');

        if ($key === '') {
            throw new RuntimeException('BLIND_INDEX_KEY is not configured.');
        }

        return $key;
    }
}
