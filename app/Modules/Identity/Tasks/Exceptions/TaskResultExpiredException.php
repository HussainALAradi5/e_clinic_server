<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * The result file was purged (kept 7 days).
 */
final class TaskResultExpiredException extends DomainException
{
    protected int $status = 410;

    protected string $errorCode = 'TASK_RESULT_EXPIRED';
}
