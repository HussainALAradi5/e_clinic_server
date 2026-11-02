<?php

declare(strict_types=1);

namespace App\Traits\Payments;

use App\Enums\Payments\InvoiceStatus;
use App\Models\Payments\InvoiceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A Chargeable's line on a live (not cancelled) invoice, so screens can show "billed on INV-…" or offer to bill it.
 *
 * @property-read ?InvoiceItem $invoiceLine
 */
trait BilledOnInvoice
{
    /**
     * @return MorphOne<InvoiceItem, $this>
     */
    public function invoiceLine(): MorphOne
    {
        return $this->morphOne(InvoiceItem::class, 'chargeable')
            ->whereHas('invoice', fn (Builder $q) => $q->where('status', '!=', InvoiceStatus::Cancelled->value));
    }

    /**
     * @return ?array{id: string, code: string, invoice_number: ?string, status: string}
     */
    public function invoiceSummary(): ?array
    {
        $invoice = $this->invoiceLine?->invoice;

        return $invoice === null ? null : [
            'id' => $invoice->id,
            'code' => $invoice->code,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
        ];
    }
}
