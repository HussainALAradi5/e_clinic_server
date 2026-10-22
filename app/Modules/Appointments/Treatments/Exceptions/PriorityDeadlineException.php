<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The first session of an urgent (24 h) or high (72 h) treatment is booked too late.
 */
final class PriorityDeadlineException extends BusinessValidationException
{
    protected string $errorCode = 'PRIORITY_DEADLINE_MISSED';
}
