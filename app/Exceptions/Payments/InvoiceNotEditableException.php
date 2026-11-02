<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * Only draft invoices can be edited or issued.
 */
final class InvoiceNotEditableException extends ConflictException
{
    protected string $errorCode = 'INVOICE_NOT_EDITABLE';
}
