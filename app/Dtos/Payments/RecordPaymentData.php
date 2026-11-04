<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

use App\Enums\Shared\PaymentMethod;

final readonly class RecordPaymentData
{
    public function __construct(
        public string $invoiceId,
        public string $amount,
        public PaymentMethod $method,
        public ?string $referenceNumber = null,
        public ?string $cardLast4 = null,
        public ?string $notes = null,
        /** The patient pays the insurer's share too (no claim for it); off = co-pay only on insured invoices. */
        public bool $payInsurerShare = false,
        /** Who takes the payment when nobody is signed in (an online capture: the one who started it). */
        public ?string $receivedBy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['invoice_id'],
            (string) $data['amount'],
            PaymentMethod::from((string) $data['method']),
            isset($data['reference_number']) ? (string) $data['reference_number'] : null,
            isset($data['card_last4']) ? (string) $data['card_last4'] : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
            (bool) ($data['pay_insurer_share'] ?? false),
        );
    }
}
