<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\GatewayCharge;
use App\Dtos\Payments\GatewayChargeRequest;

/**
 * An online card / BenefitPay gateway (Tap in production, a fake one locally). The gateway's own record is the
 * truth: webhooks only say "look again", and the charge is always fetched back before money is booked.
 */
interface PaymentGatewayInterface
{
    /** Opens a charge and returns where to send the payer (the hosted payment page). */
    public function createCharge(GatewayChargeRequest $request): GatewayCharge;

    public function retrieveCharge(string $chargeId): GatewayCharge;

    /**
     * Checks a webhook's signature against its body.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isValidWebhook(array $payload, string $signature): bool;
}
