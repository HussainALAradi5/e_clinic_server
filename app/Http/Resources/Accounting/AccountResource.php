<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
final class AccountResource extends JsonResource
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
            'type' => $this->type->value,
            'normal_balance' => $this->normal_balance->value,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ]),
            'children' => self::collection($this->whenLoaded('children')),
            'is_group' => $this->is_group,
            'is_system' => $this->is_system,
            'is_postable' => $this->isPostable(),
            'level' => $this->level,
            'description' => $this->description,
            'usage' => $this->when($this->usage !== null, fn (): array => $this->usage ?? []),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
