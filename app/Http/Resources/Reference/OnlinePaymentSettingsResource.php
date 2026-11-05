<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Dtos\Payments\OnlinePaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The online payment settings for the admin screen: the switch, the Tap API address and sources, whether a secret
 * key is saved (with a short hint, never the key), and the webhook and return addresses to register with Tap.
 *
 * @mixin OnlinePaymentSettings
 */
final class OnlinePaymentSettingsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->enabled,
            'driver' => $this->driver,
            'base_url' => $this->baseUrl,
            'source' => $this->source,
            'has_secret_key' => $this->hasSecretKey(),
            'secret_key_hint' => $this->secretKeyHint(),
            'webhook_url' => $this->webhookUrl,
            'return_url' => $this->returnUrl,
        ];
    }
}
