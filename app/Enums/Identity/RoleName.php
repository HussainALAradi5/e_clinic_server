<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * System (seeded) roles. Custom roles may be added at runtime; these cannot be renamed or deleted.
 */
enum RoleName: string implements HasLabel
{
    use EnumHelpers;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case Pharmacist = 'pharmacist';
    case HrManager = 'hr_manager';
    case Accountant = 'accountant';
    case Cashier = 'cashier';
    case Patient = 'patient';
}
