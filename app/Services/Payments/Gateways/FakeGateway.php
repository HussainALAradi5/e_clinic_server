<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Dtos\Payments\GatewayCharge;
use App\Dtos\Payments\GatewayChargeRequest;
use App\Interfaces\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Local stand-in for the gateway (driver `fake`, never in production): charges live in the cache and its
 * "payment page" is our own test checkout with Pay / Decline buttons, so the whole flow can be tried without a
 * merchant account. Webhooks are signed with a fixed test secret.
 */
final class FakeGateway implements PaymentGatewayInterface
{
    public const SECRET = 'fake-gateway-secret';

    public function createCharge(GatewayChargeRequest $request): GatewayCharge
    {
        $id = 'chg_fake_'.Str::lower(Str::random(20));
        $checkout = URL::temporarySignedRoute('fake-checkout.show', now()->addHour(), ['charge' => $id]);
        $charge = new GatewayCharge($id, 'INITIATED', $request->amount, $request->currency, $checkout, $request->reference);
        self::store($charge, $request->redirectUrl);

        return $charge;
    }

    public function retrieveCharge(string $chargeId): GatewayCharge
    {
        /** @var array{charge: GatewayCharge}|null $saved */
        $saved = Cache::get(self::key($chargeId));

        return $saved['charge'] ?? new GatewayCharge($chargeId, 'UNKNOWN', '0.000', 'BHD', null, null);
    }

    public function isValidWebhook(array $payload, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', (string) ($payload['id'] ?? '').(string) ($payload['status'] ?? ''), self::SECRET), $signature);
    }

    /** The test checkout's decision: CAPTURED or DECLINED. Returns where the payer goes next. */
    public static function decide(string $chargeId, bool $pay): ?string
    {
        /** @var array{charge: GatewayCharge, redirect: string}|null $saved */
        $saved = Cache::get(self::key($chargeId));
        if ($saved === null) {
            return null;
        }
        $c = $saved['charge'];
        self::store(new GatewayCharge($c->id, $pay ? GatewayCharge::CAPTURED : 'DECLINED', $c->amount, $c->currency, null, $c->reference,
            $pay ? null : 'Declined on the test checkout'), $saved['redirect']);

        return $saved['redirect'].(str_contains($saved['redirect'], '?') ? '&' : '?').'tap_id='.$c->id;
    }

    /** @return array{charge: GatewayCharge, redirect: string}|null */
    public static function peek(string $chargeId): ?array
    {
        return Cache::get(self::key($chargeId));
    }

    private static function store(GatewayCharge $charge, string $redirect): void
    {
        Cache::put(self::key($charge->id), ['charge' => $charge, 'redirect' => $redirect], now()->addDay());
    }

    private static function key(string $chargeId): string
    {
        return 'fake-gateway:'.$chargeId;
    }
}
