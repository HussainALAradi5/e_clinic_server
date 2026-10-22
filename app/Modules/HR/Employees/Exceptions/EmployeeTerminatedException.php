<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This employee has been terminated; the record is read-only.
 */
final class EmployeeTerminatedException extends ConflictException
{
    protected string $errorCode = 'EMPLOYEE_TERMINATED';
}
