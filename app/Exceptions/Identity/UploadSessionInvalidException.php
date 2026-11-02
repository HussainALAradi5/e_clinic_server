<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

final class UploadSessionInvalidException extends ConflictException
{
    protected string $errorCode = 'UPLOAD_SESSION_INVALID';
}
