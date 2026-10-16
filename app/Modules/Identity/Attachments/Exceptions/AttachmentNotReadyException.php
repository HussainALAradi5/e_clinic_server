<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Exceptions;

use App\Shared\Exceptions\ConflictException;

final class AttachmentNotReadyException extends ConflictException
{
    protected string $errorCode = 'ATTACHMENT_NOT_READY';
}
