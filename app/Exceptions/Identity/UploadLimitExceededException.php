<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\DomainException;

final class UploadLimitExceededException extends DomainException
{
    protected int $status = 413;

    protected string $errorCode = 'UPLOAD_LIMIT_EXCEEDED';
}
