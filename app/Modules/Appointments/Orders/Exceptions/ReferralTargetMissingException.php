<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * A referral needs a doctor, a specialty or an outside hospital.
 */
final class ReferralTargetMissingException extends BusinessValidationException
{
    protected string $errorCode = 'REFERRAL_TARGET_MISSING';
}
