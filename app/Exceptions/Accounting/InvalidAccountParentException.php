<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The parent must be a group account of the same type (and not the account itself or one below it).
 */
final class InvalidAccountParentException extends BusinessValidationException
{
    protected string $errorCode = 'ACCOUNT_INVALID_PARENT';
}
