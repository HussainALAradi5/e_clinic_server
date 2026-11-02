<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Only active, non-group accounts of the right type can be used here.
 */
final class GroupAccountNotPostableException extends BusinessValidationException
{
    protected string $errorCode = 'ACCOUNT_NOT_POSTABLE';
}
