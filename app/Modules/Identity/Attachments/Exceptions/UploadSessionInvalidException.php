<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Exceptions;

use App\Shared\Exceptions\ConflictException;

final class UploadSessionInvalidException extends ConflictException
{
    protected string $errorCode = 'UPLOAD_SESSION_INVALID';
}
