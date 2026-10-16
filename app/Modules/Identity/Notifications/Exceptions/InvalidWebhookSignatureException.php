<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidWebhookSignatureException extends DomainException
{
    protected int $status = 401;

    protected string $errorCode = 'INVALID_WEBHOOK_SIGNATURE';
}
