<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Only an active employee without a doctor profile can become a doctor.
 */
final class EmployeeNotEligibleAsDoctorException extends BusinessValidationException
{
    protected string $errorCode = 'EMPLOYEE_NOT_ELIGIBLE';
}
