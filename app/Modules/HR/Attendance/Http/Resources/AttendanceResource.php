<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Http\Resources;

use App\Modules\HR\Attendance\Models\Attendance;
use App\Shared\Http\Resources\Summary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One working day of an employee.
 *
 * @mixin Attendance
 */
final class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn (): array => Summary::employee($this->employee)),
            'date' => $this->date->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_approved' => $this->overtime_approved,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'notes' => $this->notes,
            'needs_review' => $this->needs_review,
            'is_locked' => $this->is_locked,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
