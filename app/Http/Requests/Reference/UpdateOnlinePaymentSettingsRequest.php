<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Dtos\Reference\OnlinePaymentSettingsData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOnlinePaymentSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'base_url' => ['nullable', 'url:https', 'max:255'],
            'source' => ['nullable', 'string', 'max:60'],
            'secret_key' => ['nullable', 'string', 'max:255', 'starts_with:sk_test_,sk_live_'],
            'clear_secret_key' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): OnlinePaymentSettingsData
    {
        return new OnlinePaymentSettingsData(
            $this->boolean('enabled'),
            $this->string('base_url')->trim()->value() ?: null,
            $this->string('source')->trim()->value() ?: null,
            $this->string('secret_key')->trim()->value() ?: null,
            $this->boolean('clear_secret_key'),
        );
    }
}
