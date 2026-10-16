<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Reason stored on a failed login_attempts row.
 */
enum LoginFailureReason: string implements HasLabel
{
    use EnumHelpers;

    case InvalidCredentials = 'invalid_credentials';
    case AccountLocked = 'account_locked';
    case AccountInactive = 'account_inactive';
    case TooManyAttempts = 'too_many_attempts';
    case Invalid2FaCode = 'invalid_2fa_code';
}
