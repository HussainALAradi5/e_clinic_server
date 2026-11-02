<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\InvoiceData;
use App\Interfaces\Shared\Chargeable;
use App\Models\Payments\Invoice;
use App\Utilities\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceServiceInterface
{
    /**
     * Invoices by patient, status, dates, number; patients see their own.
     *
     * @return LengthAwarePaginator<int, Invoice>|CursorPaginator<int, Invoice>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /**
     * Issued invoices with a balance (cashier desk), oldest first.
     *
     * @return LengthAwarePaginator<int, Invoice>|CursorPaginator<int, Invoice>
     */
    public function outstanding(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Invoice with lines, payments, refunds and claims. */
    public function find(string $id): Invoice;

    /** Adds manual lines to the patient's open draft (created when missing). */
    public function createDraft(InvoiceData $data): Invoice;

    /** The single billing path: adds the source's lines to the patient's open draft; null when it bills nothing. */
    public function charge(Chargeable $source): ?Invoice;

    /**
     * Takes a source's lines off the patient's draft (a returned dispensation). Lines already on an issued invoice
     * stay → InvoiceNotEditableException (cancel or refund that invoice instead). An emptied draft is removed.
     */
    public function uncharge(Chargeable $source): void;

    /** Bills a visit (checked in or later) or a clinical order by hand, e.g. pay-before-consultation at check-in. */
    public function chargeSource(string $type, string $id): Invoice;

    /** Replaces lines and notes of a draft (optimistic lock). */
    public function updateDraft(Invoice $invoice, InvoiceData $data, int $version): Invoice;

    /** `draft → issued`: gap-free number and the revenue entry (Dr receivable / Cr revenue + VAT). */
    public function issue(Invoice $invoice): Invoice;

    /** Cancels a draft, or an issued invoice without payments (reversal entry, number kept). */
    public function cancel(Invoice $invoice, string $reason): Invoice;

    /** Internal (payments, voids): adds a signed amount to `paid_amount`; recomputes balance and status. */
    public function applyPayment(Invoice $invoice, Money $amount): Invoice;

    /** Internal (refunds): adds to `refunded_amount`. */
    public function applyRefund(Invoice $invoice, Money $amount): Invoice;
}
