<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\HR\Setting;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An HR switch with its label and what it does.
 *
 * @mixin Setting
 */
final class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'key' => $this->key->value,
            'label' => $this->key->label(),
            'description' => __("enums.SettingKeyHelp.{$this->key->value}"),
            'is_enabled' => $this->is_enabled,
            'has_amount' => $this->key->hasAmount(),
            'amount' => $this->amount === null ? null : MoneyHelper::toApi($this->amount),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
