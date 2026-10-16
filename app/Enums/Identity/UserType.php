<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Discriminator on users.user_type: which subtype record exists with the same id. Rights come only from roles.
 */
enum UserType: string implements HasLabel
{
    use EnumHelpers;

    case Admin = 'admin';
    case Doctor = 'doctor';
    case MedicalStaff = 'medical_staff';
    case Pharmacist = 'pharmacist';
    case Receptionist = 'receptionist';
    case Accountant = 'accountant';
    case Cashier = 'cashier';
    case Hr = 'hr';
    case Patient = 'patient';

    public function isPatient(): bool
    {
        return $this === self::Patient;
    }
}
