<?php

declare(strict_types=1);

namespace App\Exceptions\Communication;

use App\Exceptions\Shared\ConflictException;

/** A group comes off my list only after I have left it. */
final class ChatLeaveFirstException extends ConflictException
{
    protected string $errorCode = 'CHAT_LEAVE_FIRST';
}
