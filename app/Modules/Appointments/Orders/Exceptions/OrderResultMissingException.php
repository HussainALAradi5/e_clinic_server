<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The result needs a report, and imaging needs at least one image.
 */
final class OrderResultMissingException extends BusinessValidationException
{
    protected string $errorCode = 'ORDER_RESULT_MISSING';
}
