<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

final class NotificationNotCancellableException extends ConflictException
{
    protected string $errorCode = 'NOTIFICATION_NOT_CANCELLABLE';
}
