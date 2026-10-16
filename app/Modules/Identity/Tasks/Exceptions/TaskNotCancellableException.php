<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The task is already completed or cancelled.
 */
final class TaskNotCancellableException extends ConflictException
{
    protected string $errorCode = 'TASK_NOT_CANCELLABLE';
}
