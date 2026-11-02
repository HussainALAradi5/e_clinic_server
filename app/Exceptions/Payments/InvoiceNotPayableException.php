<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * Payments are recorded on issued or partially paid invoices only.
 */
final class InvoiceNotPayableException extends ConflictException
{
    protected string $errorCode = 'INVOICE_NOT_PAYABLE';
}
