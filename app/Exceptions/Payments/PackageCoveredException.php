<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The visit is covered by the patient's paid package: a session was used instead of billing it.
 */
final class PackageCoveredException extends ConflictException
{
    protected string $errorCode = 'PACKAGE_COVERED';
}
