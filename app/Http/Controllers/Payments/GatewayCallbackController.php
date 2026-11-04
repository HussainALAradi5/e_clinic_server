<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Interfaces\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Where the gateway comes back, unauthenticated: the signed webhook, and the payer's browser after the payment
 * page. Both only trigger a fresh look at the gateway's own record (OnlinePaymentService::sync); neither books
 * money from what it was sent.
 */
final class GatewayCallbackController
{
    public function __construct(
        private readonly OnlinePaymentServiceInterface $online,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /** Tap posts the charge here with a `hashstring` signature; unsigned or unknown posts change nothing. */
    public function webhook(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        if (! $this->gateway->isValidWebhook($payload, (string) $request->header('hashstring'))) {
            return response()->json(['received' => false], 401);
        }
        if (is_string($payload['id'] ?? null)) {
            $this->online->sync($payload['id']);
        }

        return response()->json(['received' => true]);
    }

    /** The payer's browser: look the charge up, then send them back to their invoices with the outcome. */
    public function return(Request $request): RedirectResponse
    {
        $chargeId = (string) $request->query('tap_id', '');
        $online = $chargeId === '' ? null : $this->online->sync($chargeId);
        $query = http_build_query(array_filter(['online' => $online?->id, 'status' => $online?->status->value]));

        return redirect()->away(rtrim(Config::string('clinic.client_url'), '/').Config::string('payments.online.return_path').($query === '' ? '' : '?'.$query));
    }
}
