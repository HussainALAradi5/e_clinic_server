<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Http\Resources;

use App\Enums\Identity\UserType;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Doctor with name (from persons), employment summary, specialty, billed services with prices and default room.
 *
 * @mixin Doctor
 */
final class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = fn (BillableService $s): array => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'price' => MoneyHelper::toApi($s->price)];
        // Patients choosing a doctor in the portal see the public profile, not staff contact details.
        $staff = $request->user()?->getAttribute('user_type') !== UserType::Patient;

        return [
            'id' => $this->id,
            'code' => $this->person->code,
            'full_name' => $this->person->full_name,
            'display_name' => 'Dr. '.$this->person->full_name,
            'gender' => $this->person->gender->value,
            'phone' => $this->when($staff, fn (): string => $this->person->phone),
            'email' => $this->when($staff, fn (): ?string => $this->person->email),
            'job_title' => $this->whenLoaded('employee', fn (): string => $this->employee->job_title),
            'employee_status' => $this->whenLoaded('employee', fn (): string => $this->employee->status->value),
            'department' => $this->whenLoaded('employee', fn (): array => ['id' => $this->employee->department->id, 'name' => $this->employee->department->name]),
            'specialty_id' => $this->specialty_id,
            'specialty' => $this->whenLoaded('specialty', fn (): array => ['id' => $this->specialty->id, 'code' => $this->specialty->code, 'name' => $this->specialty->name]),
            'license_number' => $this->license_number,
            'license_expiry_date' => $this->license_expiry_date->toDateString(),
            'title' => $this->title,
            'consultation_service_id' => $this->consultation_service_id,
            'consultation_service' => $this->whenLoaded('consultationService', fn (): array => $service($this->consultationService)),
            'follow_up_service_id' => $this->follow_up_service_id,
            'follow_up_service' => $this->whenLoaded('followUpService', fn (): array => $service($this->followUpService)),
            'default_slot_minutes' => $this->default_slot_minutes,
            'max_daily_appointments' => $this->max_daily_appointments,
            'is_accepting_patients' => $this->is_accepting_patients,
            'is_bookable' => $this->relationLoaded('employee') ? $this->isBookable() : null,
            'default_room_id' => $this->default_room_id,
            'default_room' => $this->whenLoaded('defaultRoom', fn (): ?array => $this->defaultRoom === null ? null : ['id' => $this->defaultRoom->id, 'code' => $this->defaultRoom->code, 'name' => $this->defaultRoom->name]),
            'bio' => $this->bio,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
