<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Exceptions;

use App\Shared\Exceptions\ConflictException;

final class NotificationNotResendableException extends ConflictException
{
    protected string $errorCode = 'NOTIFICATION_NOT_RESENDABLE';
}
