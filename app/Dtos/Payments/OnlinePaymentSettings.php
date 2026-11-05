<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

/**
 * Online payments as they apply now: the clinic's switch and Tap settings (saved values first, then `.env`), and
 * the two addresses Tap must know. The secret key stays on the server; resources show only whether one is set.
 */
final readonly class OnlinePaymentSettings
{
    public function __construct(
        public bool $enabled,
        /** `tap` once a secret key is set, otherwise `payments.online.driver` (`fake` locally). */
        public string $driver,
        public string $baseUrl,
        public string $source,
        public ?string $secretKey,
        /** Where Tap posts the result (register it in the Tap dashboard). */
        public string $webhookUrl,
        /** Where the payer comes back after paying. */
        public string $returnUrl,
    ) {}

    public function hasSecretKey(): bool
    {
        return $this->secretKey !== null && $this->secretKey !== '';
    }

    /** `sk_test_…1234`: enough to recognise the key, never enough to use it. */
    public function secretKeyHint(): ?string
    {
        if (! $this->hasSecretKey()) {
            return null;
        }
        $key = (string) $this->secretKey;
        $prefix = str_starts_with($key, 'sk_test_') ? 'sk_test_' : (str_starts_with($key, 'sk_live_') ? 'sk_live_' : '');

        return $prefix.'…'.substr($key, -4);
    }
}
