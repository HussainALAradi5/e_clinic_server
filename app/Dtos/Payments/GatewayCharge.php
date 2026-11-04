<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

/**
 * A charge as the gateway reports it. `status` is the gateway's own word (Tap: INITIATED, CAPTURED, FAILED,
 * DECLINED, CANCELLED, ABANDONED, …).
 */
final readonly class GatewayCharge
{
    public const CAPTURED = 'CAPTURED';

    /** Final outcomes where no money moved. */
    public const FAILED = ['FAILED', 'DECLINED', 'CANCELLED', 'ABANDONED', 'VOID', 'RESTRICTED', 'TIMEDOUT', 'DENIED'];

    public function __construct(
        public string $id,
        public string $status,
        public string $amount,
        public string $currency,
        /** The hosted payment page while the charge waits for the payer. */
        public ?string $checkoutUrl,
        public ?string $reference,
        public ?string $failureReason = null,
    ) {}

    public function isCaptured(): bool
    {
        return $this->status === self::CAPTURED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, self::FAILED, true);
    }
}
