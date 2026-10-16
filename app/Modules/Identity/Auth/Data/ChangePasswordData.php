<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Data;

final readonly class ChangePasswordData
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}
}
