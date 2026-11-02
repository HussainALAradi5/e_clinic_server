<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\HR\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A year's days of one leave type; `remaining` is null for types without a limit.
 *
 * @mixin LeaveBalance
 */
final class LeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'leave_type' => new LeaveTypeResource($this->leaveType),
            'entitled_days' => (string) $this->entitled_days,
            'carried_forward_days' => (string) $this->carried_forward_days,
            'used_days' => (string) $this->used_days,
            'pending_days' => (string) $this->pending_days,
            'remaining_days' => $this->leaveType->isLimited() ? (string) $this->remaining()->toScale(1) : null,
        ];
    }
}
