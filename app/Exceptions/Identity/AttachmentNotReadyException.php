<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

final class AttachmentNotReadyException extends ConflictException
{
    protected string $errorCode = 'ATTACHMENT_NOT_READY';
}
