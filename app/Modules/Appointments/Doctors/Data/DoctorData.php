<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Data;

use Illuminate\Support\Arr;

/**
 * Doctor profile input (`employee_id` on create only); on update only the provided keys change.
 */
final readonly class DoctorData
{
    private const FIELDS = ['specialty_id', 'license_number', 'license_expiry_date', 'title', 'consultation_service_id', 'follow_up_service_id', 'default_slot_minutes', 'max_daily_appointments', 'default_room_id', 'bio'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values, public ?string $employeeId) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)),
            isset($input['employee_id']) ? (string) $input['employee_id'] : null,
        );
    }
}
