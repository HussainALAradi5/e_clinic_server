<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Action recorded in audit_logs.
 */
enum AuditAction: string implements HasLabel
{
    use EnumHelpers;

    case Viewed = 'viewed';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case StatusChanged = 'status_changed';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordChanged = 'password_changed';
    case PermissionChanged = 'permission_changed';
    case Exported = 'exported';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Approved = 'approved';
    // Break the glass: a clinician opened a patient outside their care.
    case EmergencyAccess = 'emergency_access';
}
