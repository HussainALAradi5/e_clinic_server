<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

final class AttachmentTypeNotAllowedException extends BusinessValidationException
{
    protected string $errorCode = 'ATTACHMENT_TYPE_NOT_ALLOWED';
}
