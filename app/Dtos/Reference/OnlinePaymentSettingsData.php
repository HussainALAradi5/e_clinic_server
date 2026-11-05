<?php

declare(strict_types=1);

namespace App\Dtos\Reference;

/**
 * An admin's change to the online payment settings. A null secret key keeps the saved one; `clearSecretKey` removes it.
 */
final readonly class OnlinePaymentSettingsData
{
    public function __construct(
        public bool $enabled,
        public ?string $baseUrl,
        public ?string $source,
        public ?string $secretKey,
        public bool $clearSecretKey,
    ) {}
}
