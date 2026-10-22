<?php

declare(strict_types=1);

namespace App\Modules\HR\Departments\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The department (or one below it) still has employees. Move them first or archive the department.
 */
final class DepartmentHasEmployeesException extends ConflictException
{
    protected string $errorCode = 'DEPARTMENT_HAS_EMPLOYEES';
}
