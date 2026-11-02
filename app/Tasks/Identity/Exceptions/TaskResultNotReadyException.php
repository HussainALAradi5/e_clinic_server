<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Exceptions;

use App\Exceptions\Shared\ConflictException;

/**
 * Download requested before the task completed.
 */
final class TaskResultNotReadyException extends ConflictException
{
    protected string $errorCode = 'TASK_RESULT_NOT_READY';
}
