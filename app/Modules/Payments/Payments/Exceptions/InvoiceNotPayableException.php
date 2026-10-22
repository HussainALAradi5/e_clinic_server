<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Payments are recorded on issued or partially paid invoices only.
 */
final class InvoiceNotPayableException extends ConflictException
{
    protected string $errorCode = 'INVOICE_NOT_PAYABLE';
}
