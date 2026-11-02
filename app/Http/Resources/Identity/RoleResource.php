<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'display_name_ar' => $this->display_name_ar,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'users_count' => $this->users_count,
            'permissions_count' => $this->permissions_count,
            'permissions' => $this->whenLoaded('permissions', fn (): array => array_values($this->permissions->pluck('name')->sort()->all())),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
