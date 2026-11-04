<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The package is taken off sale; patients who bought it keep their sessions.
 */
final class PackageNotOnSaleException extends ConflictException
{
    protected string $errorCode = 'PACKAGE_NOT_ON_SALE';
}
