<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Only an active employee without a doctor profile can become a doctor.
 */
final class EmployeeNotEligibleAsDoctorException extends BusinessValidationException
{
    protected string $errorCode = 'EMPLOYEE_NOT_ELIGIBLE';
}
