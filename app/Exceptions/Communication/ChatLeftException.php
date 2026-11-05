<?php

declare(strict_types=1);

namespace App\Exceptions\Communication;

use App\Exceptions\Shared\ConflictException;

/** I left this group: I can still read it up to when I left, not write in it. */
final class ChatLeftException extends ConflictException
{
    protected string $errorCode = 'CHAT_LEFT';
}
