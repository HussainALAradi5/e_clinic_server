<?php

declare(strict_types=1);

namespace App\Utilities\Shared;

/**
 * Central list of cache keys, so every writer and reader invalidates the same entry.
 */
final class CacheKeys
{
    /** Effective access (roles + permissions) of one user, 10 minutes. */
    public static function permissions(string $userId): string
    {
        return "perm:{$userId}";
    }

    /** Pending 2FA login challenge, 5 minutes. */
    public static function twoFactorChallenge(string $token): string
    {
        return '2fa:challenge:'.hash('sha256', $token);
    }

    /** Pending "forgot password" code of an email, 10 minutes. */
    public static function passwordResetCode(string $email): string
    {
        return 'password:code:'.hash('sha256', mb_strtolower(trim($email)));
    }

    /** Account mappings of automatic postings (forever, flushed when they change). */
    public static function accountMappings(): string
    {
        return 'accounting:mappings';
    }

    /** Failed-login limiter (RateLimiter key). */
    public static function loginAttempts(string $email, string $ip): string
    {
        return "login:{$email}|{$ip}";
    }
}
