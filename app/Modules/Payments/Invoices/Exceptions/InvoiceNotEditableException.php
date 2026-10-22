<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Only draft invoices can be edited or issued.
 */
final class InvoiceNotEditableException extends ConflictException
{
    protected string $errorCode = 'INVOICE_NOT_EDITABLE';
}
