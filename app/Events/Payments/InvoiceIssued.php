<?php

declare(strict_types=1);

namespace App\Events\Payments;

use App\Models\Payments\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

final class InvoiceIssued
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
