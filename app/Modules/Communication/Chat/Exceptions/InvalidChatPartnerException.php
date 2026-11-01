<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/** Chats are between active staff members. */
final class InvalidChatPartnerException extends BusinessValidationException
{
    protected string $errorCode = 'CHAT_INVALID_PARTNER';
}
