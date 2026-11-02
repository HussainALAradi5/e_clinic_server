<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\ScheduleException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScheduleException
 */
final class ScheduleExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'provider_id' => $this->provider_id,
            'is_clinic_wide' => $this->isClinicWide(),
            'date' => $this->date->toDateString(),
            'type' => $this->type->value,
            'start_time' => $this->start_time !== null ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time !== null ? substr($this->end_time, 0, 5) : null,
            'reason' => $this->reason,
        ];
    }
}
