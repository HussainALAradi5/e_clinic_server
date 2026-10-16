<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Data;

final readonly class ResetPasswordData
{
    public function __construct(
        public string $token,
        public string $email,
        public string $password,
    ) {}
}
