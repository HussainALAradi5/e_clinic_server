<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * This person is already an employee.
 */
final class AlreadyEmployeeException extends ConflictException
{
    protected string $errorCode = 'ALREADY_EMPLOYEE';
}
