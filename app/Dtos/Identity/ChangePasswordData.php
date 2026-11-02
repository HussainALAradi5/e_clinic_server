<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

final readonly class ChangePasswordData
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}
}
