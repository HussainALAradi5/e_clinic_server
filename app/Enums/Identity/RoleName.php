<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

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
    /** The support team: sees and answers every support ticket. */
    case Support = 'support';
    case Patient = 'patient';
}
