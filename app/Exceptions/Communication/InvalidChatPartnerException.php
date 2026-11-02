<?php

declare(strict_types=1);

namespace App\Exceptions\Communication;

use App\Exceptions\Shared\BusinessValidationException;

/** Chats are between active staff members. */
final class InvalidChatPartnerException extends BusinessValidationException
{
    protected string $errorCode = 'CHAT_INVALID_PARTNER';
}
