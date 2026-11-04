<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Models\Payments\Invoice;
use App\Models\Payments\OnlinePayment;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paying invoices online through the gateway. Rules: only payable invoices; at least `payments.online.min_amount`
 * and at most what the patient owes now (their co-pay on an insured invoice); one attempt per idempotency key.
 * Money is booked only after the gateway's own record says CAPTURED for the same amount and currency — never from
 * a webhook body or a return URL alone. A surplus goes to the patient's balance.
 */
interface OnlinePaymentServiceInterface
{
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): OnlinePayment;

    /** Opens a gateway charge for the invoice (all the patient owes, or `amount`) and returns it with its checkout URL. */
    public function start(Invoice $invoice, ?string $amount, string $idempotencyKey): OnlinePayment;

    /** Asks the gateway how the charge stands and books it once captured; safe to call any number of times. */
    public function sync(string $chargeId): ?OnlinePayment;

    /** Gives up on attempts left on the payment page past their expiry (after one last look at the gateway). */
    public function expireStale(): int;
}
