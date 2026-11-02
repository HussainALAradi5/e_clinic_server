<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The invoice needs at least one line with a positive total.
 */
final class EmptyInvoiceException extends BusinessValidationException
{
    protected string $errorCode = 'INVOICE_EMPTY';
}
