<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * Only staff sign documents; patients keep no signature.
 */
final class SignatureNotAllowedException extends ForbiddenActionException
{
    protected string $errorCode = 'SIGNATURE_STAFF_ONLY';
}
