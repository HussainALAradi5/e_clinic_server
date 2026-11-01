<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Exceptions;

use App\Shared\Exceptions\ConflictException;

/** The chat was deleted: nobody writes in it until an admin restores it. */
final class ChatDeletedException extends ConflictException
{
    protected string $errorCode = 'CHAT_DELETED';
}
