<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * An invoice with completed payments cannot be cancelled; refund or void them first.
 */
final class InvoiceHasPaymentsException extends ConflictException
{
    protected string $errorCode = 'INVOICE_HAS_PAYMENTS';
}
