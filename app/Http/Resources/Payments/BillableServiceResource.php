<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\BillableService;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillableService
 */
final class BillableServiceResource extends JsonResource
{
    /** @var array<string, int>|null */
    private ?array $usage = null;

    /**
     * @param  array<string, int>  $usage
     */
    public function withUsage(array $usage): self
    {
        $this->usage = $usage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'category' => $this->category->value,
            'price' => MoneyHelper::toApi($this->price),
            'tax_rate' => $this->tax_rate,
            'revenue_account_id' => $this->revenue_account_id,
            'revenue_account' => $this->whenLoaded('revenueAccount', fn (): array => ['id' => $this->revenueAccount->id, 'code' => $this->revenueAccount->code, 'name' => $this->revenueAccount->name]),
            'specialty_id' => $this->specialty_id,
            'specialty' => $this->whenLoaded('specialty', fn (): ?array => $this->specialty === null ? null : ['id' => $this->specialty->id, 'code' => $this->specialty->code, 'name' => $this->specialty->name]),
            'is_insurable' => $this->is_insurable,
            'consent_type' => $this->consent_type?->value,
            'usage' => $this->when($this->usage !== null, fn (): array => $this->usage ?? []),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
