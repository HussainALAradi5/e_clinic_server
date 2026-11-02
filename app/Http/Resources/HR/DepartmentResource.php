<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\HR\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
final class DepartmentResource extends JsonResource
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
            'name_ar' => $this->name_ar,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent === null ? null : ['id' => $this->parent->id, 'code' => $this->parent->code, 'name' => $this->parent->name]),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager === null ? null : [
                'id' => $this->manager->id,
                'full_name' => $this->manager->user->person->full_name,
                'job_title' => $this->manager->job_title,
            ]),
            'cost_center_code' => $this->cost_center_code,
            'employees_count' => $this->whenCounted('employees'),
            'children' => self::collection($this->whenLoaded('children')),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
