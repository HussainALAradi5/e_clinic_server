<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

final class AttachmentTypeNotAllowedException extends BusinessValidationException
{
    protected string $errorCode = 'ATTACHMENT_TYPE_NOT_ALLOWED';
}
