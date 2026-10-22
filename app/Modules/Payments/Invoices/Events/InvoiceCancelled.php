<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Events;

use App\Modules\Payments\Invoices\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

final class InvoiceCancelled
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
