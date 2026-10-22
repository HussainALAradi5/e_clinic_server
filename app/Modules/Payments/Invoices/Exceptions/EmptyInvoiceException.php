<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The invoice needs at least one line with a positive total.
 */
final class EmptyInvoiceException extends BusinessValidationException
{
    protected string $errorCode = 'INVOICE_EMPTY';
}
