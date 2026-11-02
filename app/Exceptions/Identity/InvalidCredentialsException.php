<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\DomainException;

/**
 * Email / password combination is wrong (generic message — never says which part).
 */
final class InvalidCredentialsException extends DomainException
{
    protected int $status = 401;

    protected string $errorCode = 'AUTH_INVALID_CREDENTIALS';
}
