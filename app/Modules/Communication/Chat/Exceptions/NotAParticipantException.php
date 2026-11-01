<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Exceptions;

use App\Shared\Exceptions\ForbiddenActionException;

/** Only members of a chat can read or write in it. */
final class NotAParticipantException extends ForbiddenActionException
{
    protected string $errorCode = 'CHAT_NOT_A_MEMBER';
}
