<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
final class RoomResource extends JsonResource
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
            'type' => $this->type->value,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (): ?array => $this->department === null ? null : ['id' => $this->department->id, 'name' => $this->department->name]),
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'is_in_service' => $this->is_in_service,
            'out_of_service_reason' => $this->out_of_service_reason,
            'notes' => $this->notes,
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
