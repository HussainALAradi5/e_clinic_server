<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Resources\Payments\OnlinePaymentResource;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Models\Payments\OnlinePayment;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paying an invoice online: start (returns the gateway's payment page), follow its status, list attempts.
 */
final class OnlinePaymentController
{
    use ApiResponse;

    public function __construct(
        private readonly OnlinePaymentServiceInterface $online,
        private readonly InvoiceServiceInterface $invoices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, OnlinePayment::listQueryConfig());

        return $this->paginated(OnlinePaymentResource::collection($this->online->list($query)), $query);
    }

    /** The attempt as it stands; asks the gateway again while it still waits (the page polls this after returning). */
    public function show(string $payment): JsonResponse
    {
        $online = $this->online->find($payment);
        if (! $online->status->isFinal() && $online->charge_id !== null) {
            $this->online->sync($online->charge_id);
            $online = $this->online->find($payment);
        }

        return $this->ok(new OnlinePaymentResource($online));
    }

    /** Opens a charge for what the patient owes on the invoice (or `amount`); send the payer to `checkout_url`. */
    public function start(Request $request, string $invoice): JsonResponse
    {
        $request->validate(['amount' => ['nullable', 'decimal:0,3', 'gt:0']]);
        $online = $this->online->start($this->invoices->find($invoice), $request->input('amount'), (string) $request->header('Idempotency-Key'));

        return $this->created(new OnlinePaymentResource($online));
    }
}
