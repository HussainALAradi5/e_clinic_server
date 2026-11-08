<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;

/**
 * Login account added to an existing person (employee hire, patient portal account, first super admin).
 */
final readonly class CreateUserData
{
    /**
     * @param  list<string>  $roles  role names
     * @param  bool  $isRoleFixedBySystem  the system picks the roles, not the caller (a patient's own portal account)
     */
    public function __construct(
        public UserType $userType,
        public array $roles = [],
        public ?string $password = null,
        public UserStatus $status = UserStatus::Active,
        public bool $isRoleFixedBySystem = false,
    ) {}
}
