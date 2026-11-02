<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * A referral needs a doctor, a specialty or an outside hospital.
 */
final class ReferralTargetMissingException extends BusinessValidationException
{
    protected string $errorCode = 'REFERRAL_TARGET_MISSING';
}
