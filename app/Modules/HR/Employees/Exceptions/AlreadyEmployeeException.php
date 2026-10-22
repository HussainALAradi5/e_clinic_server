<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This person is already an employee.
 */
final class AlreadyEmployeeException extends ConflictException
{
    protected string $errorCode = 'ALREADY_EMPLOYEE';
}
