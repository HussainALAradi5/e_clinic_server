<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Data;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentType;
use Carbon\CarbonImmutable;

final readonly class BookAppointmentData
{
    public function __construct(
        public string $patientId,
        public string $providerId,
        public AppointmentType $type,
        public CarbonImmutable $date,
        public string $startTime,
        public AppointmentSource $source,
        public ?string $endTime = null,
        public ?string $roomId = null,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $treatmentId = null,
        public ?string $rescheduledFromId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $string = fn (string $key): ?string => isset($input[$key]) && $input[$key] !== '' ? (string) $input[$key] : null;

        return new self(
            patientId: (string) $input['patient_id'],
            providerId: (string) $input['provider_id'],
            type: AppointmentType::from((string) $input['type']),
            date: CarbonImmutable::parse((string) $input['scheduled_date'])->startOfDay(),
            startTime: (string) $input['start_time'],
            source: AppointmentSource::from((string) $input['source']),
            endTime: $string('end_time'),
            roomId: $string('room_id'),
            reason: $string('reason'),
            notes: $string('notes'),
            treatmentId: $string('treatment_id'),
        );
    }
}
