<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

final readonly class PushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $publicKey,
        public string $authToken,
        public ?string $userAgent = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input (the browser's subscription JSON)
     */
    public static function fromArray(array $input, ?string $userAgent = null): self
    {
        /** @var array{p256dh: string, auth: string} $keys */
        $keys = $input['keys'];

        return new self(
            endpoint: (string) $input['endpoint'],
            publicKey: $keys['p256dh'],
            authToken: $keys['auth'],
            userAgent: $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        );
    }
}
