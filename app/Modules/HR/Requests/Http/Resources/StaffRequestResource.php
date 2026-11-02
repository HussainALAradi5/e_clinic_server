<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Http\Resources;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Http\Resources\Summary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff request with its day, the minutes (live while pending, as decided afterwards) and the decision.
 *
 * @mixin StaffRequest
 */
final class StaffRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn (): array => Summary::employee($this->employee)),
            'attendance' => $this->whenLoaded('attendance', fn (): array => [
                'id' => $this->attendance->id,
                'date' => $this->attendance->date->toDateString(),
                'check_in_at' => $this->attendance->check_in_at?->toIso8601String(),
                'check_out_at' => $this->attendance->check_out_at?->toIso8601String(),
                'worked_minutes' => $this->attendance->worked_minutes,
                'status' => $this->attendance->status->value,
            ]),
            // While pending, an HR correction of the day shows at once; the decision keeps what was decided.
            'minutes' => $this->status === ApprovalStatus::Pending && $this->relationLoaded('attendance')
                ? ($this->type === StaffRequestType::Overtime ? $this->attendance->overtime_minutes : $this->attendance->late_minutes)
                : $this->minutes(),
            'details' => $this->details,
            'reason' => $this->reason,
            'reviewed_by' => $this->whenLoaded('reviewer', fn (): ?array => Summary::user($this->reviewer)),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_reason' => $this->review_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
