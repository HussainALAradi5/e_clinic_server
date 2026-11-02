<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Http\Resources\Shared\Summary;
use App\Models\HR\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Leave request with its type, requester and decision.
 *
 * @mixin LeaveRequest
 */
final class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn (): array => Summary::employee($this->employee)),
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn (): LeaveTypeResource => new LeaveTypeResource($this->leaveType)),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'total_days' => (string) $this->total_days,
            'is_half_day' => $this->is_half_day,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'reviewed_by' => $this->whenLoaded('reviewer', fn (): ?array => Summary::user($this->reviewer)),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'attachments_count' => $this->whenLoaded('attachments', fn (): int => $this->attachments->where('is_deleted', false)->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
