<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * More than what remains on the prescription line.
 */
final class DispenseQuantityExceededException extends BusinessValidationException
{
    protected string $errorCode = 'DISPENSE_QUANTITY_EXCEEDED';
}
