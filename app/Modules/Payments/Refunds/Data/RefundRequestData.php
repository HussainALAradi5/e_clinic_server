<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Data;

use App\Shared\Enums\PaymentMethod;

final readonly class RefundRequestData
{
    public function __construct(
        public string $paymentId,
        public string $amount,
        public ?PaymentMethod $method,
        public string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['payment_id'],
            (string) $data['amount'],
            isset($data['method']) ? PaymentMethod::from((string) $data['method']) : null,
            (string) $data['reason'],
        );
    }
}
