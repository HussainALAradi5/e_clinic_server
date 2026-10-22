<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Http\Resources;

use App\Modules\HR\Leaves\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
final class LeaveTypeResource extends JsonResource
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
            'annual_quota_days' => (string) $this->annual_quota_days,
            'is_paid' => $this->is_paid,
            'is_limited' => $this->isLimited(),
            'requires_attachment' => $this->requires_attachment,
            'max_carry_forward_days' => (string) $this->max_carry_forward_days,
            'min_notice_days' => $this->min_notice_days,
            'is_archived' => $this->is_archived,
        ];
    }
}
