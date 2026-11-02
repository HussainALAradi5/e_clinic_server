<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Illuminate\Support\Arr;

/**
 * Validated treatment input; `sessions` are first bookings made with the prescription.
 */
final readonly class TreatmentData
{
    public const FIELDS = ['patient_id', 'doctor_id', 'appointment_id', 'type', 'billable_service_id', 'title', 'diagnosis', 'plan', 'priority', 'planned_sessions', 'session_duration_minutes'];

    /**
     * @param  array<string, mixed>  $values
     * @param  list<SessionBookingData>  $sessions
     */
    private function __construct(public array $values, public array $sessions, public bool $overrideDeadline) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $sessions = array_map(fn (array $s): SessionBookingData => SessionBookingData::fromArray($s), array_values((array) ($input['sessions'] ?? [])));

        return new self(
            array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)),
            $sessions,
            (bool) ($input['override_priority_deadline'] ?? false),
        );
    }
}
