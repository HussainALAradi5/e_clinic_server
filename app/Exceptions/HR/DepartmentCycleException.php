<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The chosen parent would put the department under itself.
 */
final class DepartmentCycleException extends BusinessValidationException
{
    protected string $errorCode = 'DEPARTMENT_CYCLE';
}
