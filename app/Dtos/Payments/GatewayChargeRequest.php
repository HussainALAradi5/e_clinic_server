<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

/**
 * What the clinic asks the gateway to charge: amount in the currency's decimals (BHD: 3), who pays, and where
 * the payer and the webhook come back.
 */
final readonly class GatewayChargeRequest
{
    public function __construct(
        public string $amount,
        public string $currency,
        /** Our reference (the online payment code), echoed back by the gateway. */
        public string $reference,
        public string $description,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public string $redirectUrl,
        public string $webhookUrl,
    ) {}
}
