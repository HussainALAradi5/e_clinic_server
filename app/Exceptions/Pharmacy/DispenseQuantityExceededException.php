<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * More than what remains on the prescription line.
 */
final class DispenseQuantityExceededException extends BusinessValidationException
{
    protected string $errorCode = 'DISPENSE_QUANTITY_EXCEEDED';
}
