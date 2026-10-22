<?php

declare(strict_types=1);

namespace App\Modules\HR\Departments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The chosen parent would put the department under itself.
 */
final class DepartmentCycleException extends BusinessValidationException
{
    protected string $errorCode = 'DEPARTMENT_CYCLE';
}
