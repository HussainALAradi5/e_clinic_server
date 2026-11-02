<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * The department (or one below it) still has employees. Move them first or archive the department.
 */
final class DepartmentHasEmployeesException extends ConflictException
{
    protected string $errorCode = 'DEPARTMENT_HAS_EMPLOYEES';
}
