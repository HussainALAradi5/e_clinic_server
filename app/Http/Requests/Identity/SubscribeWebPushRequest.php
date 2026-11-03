<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\PushSubscriptionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The browser's PushSubscription as JSON: `{ endpoint, keys: { p256dh, auth } }`.
 */
final class SubscribeWebPushRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): PushSubscriptionData
    {
        return PushSubscriptionData::fromArray($this->validated(), $this->userAgent());
    }
}
