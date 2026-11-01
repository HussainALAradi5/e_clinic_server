<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Http\Resources;

use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Shifts\Models\Shift;
use App\Modules\Identity\Users\Support\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shift and, when loaded, the people on it.
 *
 * @mixin Shift
 */
final class ShiftResource extends JsonResource
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
            'type' => $this->type->value,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'latest_start' => $this->latest_start === null ? null : substr($this->latest_start, 0, 5),
            'required_minutes' => $this->required_minutes,
            'arrive_by' => $this->arriveBy(),
            'notes' => $this->notes,
            'employees_count' => $this->whenCounted('employees'),
            'employees' => $this->whenLoaded('employees', fn (): array => $this->employees->map(fn (Employee $e): array => [
                'id' => $e->id,
                'code' => $e->person->code,
                'full_name' => $e->person->full_name,
                'avatar_url' => Avatar::url($e->person),
                'job_title' => $e->job_title,
                'department' => $e->department?->name,
            ])->values()->all()),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
