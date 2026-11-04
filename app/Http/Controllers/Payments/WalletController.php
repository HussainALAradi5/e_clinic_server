<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\WalletMovementRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Http\Resources\Payments\WalletEntryResource;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\WalletServiceInterface;
use App\Models\Payments\PatientWalletEntry;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient balance: see it, pay money in, pay it back, correct it, and pay invoices from it.
 */
final class WalletController
{
    use ApiResponse;

    public function __construct(
        private readonly WalletServiceInterface $wallet,
        private readonly PatientServiceInterface $patients,
        private readonly InvoiceServiceInterface $invoices,
    ) {}

    /** The balance and its movements (newest first); `meta.balance` is the current balance. */
    public function show(Request $request, string $patient): JsonResponse
    {
        $record = $this->patients->find($patient);
        $query = ListQuery::fromRequest($request, PatientWalletEntry::listQueryConfig());

        return $this->paginated(
            WalletEntryResource::collection($this->wallet->entries($record, $query)),
            $query,
            extraMeta: ['balance' => MoneyHelper::toApi($this->wallet->balance($record))],
        );
    }

    public function deposit(WalletMovementRequest $request, string $patient): JsonResponse
    {
        return $this->created(new WalletEntryResource($this->wallet->deposit($this->patients->find($patient), $request->toData(), $this->key($request))));
    }

    public function withdraw(WalletMovementRequest $request, string $patient): JsonResponse
    {
        return $this->created(new WalletEntryResource($this->wallet->withdraw($this->patients->find($patient), $request->toData(), $this->key($request))));
    }

    public function adjust(WalletMovementRequest $request, string $patient): JsonResponse
    {
        return $this->created(new WalletEntryResource($this->wallet->adjust($this->patients->find($patient), $request->toData(), $request->isCredit())));
    }

    /** Pays the invoice from the patient's balance: all that is due (or `amount`), within the balance. */
    public function payInvoice(Request $request, string $invoice): JsonResponse
    {
        $request->validate(['amount' => ['nullable', 'decimal:0,3', 'gt:0']]);
        $payment = $this->wallet->payInvoice($this->invoices->find($invoice), $request->input('amount'), $this->key($request));

        return $this->created(new PaymentResource($payment->load('invoice')));
    }

    private function key(Request $request): string
    {
        return (string) $request->header('Idempotency-Key');
    }
}
