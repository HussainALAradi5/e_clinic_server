<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One session of a plan: number, time and length, provider, room, status, attendance and whether a note exists.
 * Medicines prescribed / given in the session join with the Pharmacy module.
 *
 * @mixin Appointment
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'session_number' => $this->session_number,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'duration_minutes' => (int) $this->startsAt()->diffInMinutes($this->endsAt()),
            'provider' => ['id' => $this->provider->id, 'full_name' => $this->provider->person->full_name],
            'room' => $this->room === null ? null : ['id' => $this->room->id, 'code' => $this->room->code, 'name' => $this->room->name],
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn ($s): string => $s->value, $this->status::transitions()[$this->status->value]),
            'is_attended' => $this->is_attended,
            'starts_at' => $this->startsAt()->format('Y-m-d\TH:i:s'),
            'has_note' => (bool) ($this->getAttribute('has_note') ?? false),
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }
}
