<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Carbon\CarbonImmutable;

/**
 * One session to book for a treatment (patient, type and service come from the plan).
 */
final readonly class SessionBookingData
{
    public function __construct(
        public string $providerId,
        public CarbonImmutable $date,
        public string $startTime,
        public ?string $endTime = null,
        public ?string $roomId = null,
        public ?string $reason = null,
        public ?string $notes = null,
        public bool $overrideDeadline = false,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $string = fn (string $key): ?string => isset($input[$key]) && $input[$key] !== '' ? (string) $input[$key] : null;

        return new self(
            providerId: (string) $input['provider_id'],
            date: CarbonImmutable::parse((string) $input['scheduled_date'])->startOfDay(),
            startTime: (string) $input['start_time'],
            endTime: $string('end_time'),
            roomId: $string('room_id'),
            reason: $string('reason'),
            notes: $string('notes'),
            overrideDeadline: (bool) ($input['override_priority_deadline'] ?? false),
        );
    }
}
