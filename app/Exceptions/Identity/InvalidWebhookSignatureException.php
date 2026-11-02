<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\DomainException;

final class InvalidWebhookSignatureException extends DomainException
{
    protected int $status = 401;

    protected string $errorCode = 'INVALID_WEBHOOK_SIGNATURE';
}
