<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * This employee has been terminated; the record is read-only.
 */
final class EmployeeTerminatedException extends ConflictException
{
    protected string $errorCode = 'EMPLOYEE_TERMINATED';
}
