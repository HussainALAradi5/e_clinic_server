<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Email / password combination is wrong (generic message — never says which part).
 */
final class InvalidCredentialsException extends DomainException
{
    protected int $status = 401;

    protected string $errorCode = 'AUTH_INVALID_CREDENTIALS';
}
