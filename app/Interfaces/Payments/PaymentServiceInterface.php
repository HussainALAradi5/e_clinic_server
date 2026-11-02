<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\RecordPaymentData;
use App\Models\Payments\Payment;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Payment>|CursorPaginator<int, Payment>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Payment with invoice and refunds. */
    public function find(string $id): Payment;

    /** Records one payment (call again per method for a split payment); idempotent per key. */
    public function record(RecordPaymentData $data, string $idempotencyKey): Payment;

    /** Same-day void of a mistaken payment: reversal entry, invoice balance restored. */
    public function void(Payment $payment, string $reason): Payment;

    /**
     * End-of-day totals per method and cashier.
     *
     * @return array{date: string, rows: list<array{method: string, received_by: string, cashier: string, count: int, total: string}>, totals: array<string, string>, grand_total: string, refunds: string}
     */
    public function dailySummary(CarbonImmutable $date, ?string $cashierId = null): array;
}
