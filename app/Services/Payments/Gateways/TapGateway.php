<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Dtos\Payments\GatewayCharge;
use App\Dtos\Payments\GatewayChargeRequest;
use App\Dtos\Payments\OnlinePaymentSettings;
use App\Exceptions\Payments\OnlinePaymentUnavailableException;
use App\Interfaces\Payments\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tap Payments (Bahrain: BenefitPay, Benefit debit, cards in BHD). Charges are created on Tap's hosted page
 * (`source: src_all`), the payer returns to `redirect.url`, and Tap posts the result to `post.url` signed with
 * HMAC-SHA256 of x_id…x_amount…x_currency…x_gateway_reference…x_payment_reference…x_status…x_created using the
 * secret key (`hashstring` header). The secret key never leaves the server.
 */
final class TapGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly OnlinePaymentSettings $settings) {}

    public function createCharge(GatewayChargeRequest $request): GatewayCharge
    {
        return $this->call(fn (PendingRequest $http) => $http->post('/charges', [
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'customer_initiated' => true,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $request->description,
            'reference' => ['transaction' => $request->reference, 'order' => $request->reference],
            'receipt' => ['email' => $request->email !== null, 'sms' => $request->phone !== null],
            'customer' => array_filter([
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
                'email' => $request->email,
                'phone' => $this->phone($request->phone),
            ]),
            'source' => ['id' => $this->settings->source],
            'post' => ['url' => $request->webhookUrl],
            'redirect' => ['url' => $request->redirectUrl],
        ]));
    }

    public function retrieveCharge(string $chargeId): GatewayCharge
    {
        return $this->call(fn (PendingRequest $http) => $http->get('/charges/'.rawurlencode($chargeId)));
    }

    public function isValidWebhook(array $payload, string $signature): bool
    {
        $secret = (string) $this->settings->secretKey;
        if ($secret === '' || $signature === '') {
            return false;
        }
        $amount = number_format((float) ($payload['amount'] ?? 0), $this->decimals((string) ($payload['currency'] ?? 'BHD')), '.', '');
        $signed = 'x_id'.($payload['id'] ?? '')
            .'x_amount'.$amount
            .'x_currency'.($payload['currency'] ?? '')
            .'x_gateway_reference'.($payload['reference']['gateway'] ?? '')
            .'x_payment_reference'.($payload['reference']['payment'] ?? '')
            .'x_status'.($payload['status'] ?? '')
            .'x_created'.($payload['transaction']['created'] ?? '');

        return hash_equals(hash_hmac('sha256', $signed, $secret), $signature);
    }

    /** @param  callable(PendingRequest): Response  $send */
    private function call(callable $send): GatewayCharge
    {
        try {
            $response = $send(Http::baseUrl($this->settings->baseUrl)
                ->withToken((string) $this->settings->secretKey)
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 300, throw: false));
            $response->throw();
        } catch (Throwable $e) {
            report($e);

            throw new OnlinePaymentUnavailableException;
        }
        $body = (array) $response->json();

        return new GatewayCharge(
            id: (string) ($body['id'] ?? ''),
            status: strtoupper((string) ($body['status'] ?? 'UNKNOWN')),
            amount: number_format((float) ($body['amount'] ?? 0), $this->decimals((string) ($body['currency'] ?? 'BHD')), '.', ''),
            currency: (string) ($body['currency'] ?? ''),
            checkoutUrl: $body['transaction']['url'] ?? null,
            reference: $body['reference']['transaction'] ?? null,
            failureReason: $body['response']['message'] ?? null,
        );
    }

    /** @return array{country_code: string, number: string}|null */
    private function phone(?string $e164): ?array
    {
        if ($e164 === null || ! preg_match('/^\+(973|966|965|968|971|974)(\d+)$/', $e164, $m)) {
            return null;
        }

        return ['country_code' => $m[1], 'number' => $m[2]];
    }

    /** Minor-unit digits Tap uses per currency (Gulf dinars: 3). */
    private function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), ['BHD', 'KWD', 'OMR', 'JOD'], true) ? 3 : 2;
    }
}
