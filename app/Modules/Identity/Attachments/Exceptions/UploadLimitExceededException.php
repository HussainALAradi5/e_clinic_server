<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class UploadLimitExceededException extends DomainException
{
    protected int $status = 413;

    protected string $errorCode = 'UPLOAD_LIMIT_EXCEEDED';
}
