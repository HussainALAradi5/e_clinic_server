<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

use Illuminate\Support\Str;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
        public string $ip,
        public ?string $userAgent = null,
        /** Token of a device trusted after an earlier 2FA code (skips the code). */
        public ?string $trustedDevice = null,
    ) {}

    /** Trimmed, lower-cased email. */
    public function normalisedEmail(): string
    {
        return Str::lower(trim($this->email));
    }
}
