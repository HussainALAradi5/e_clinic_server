<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

/**
 * Closing a visit: extra services performed (charged by billing) and an optional follow-up suggestion.
 * The medical record is saved through Medical Records (Phase 3).
 */
final readonly class CompleteAppointmentData
{
    /**
     * @param  list<string>  $billableServiceIds
     */
    public function __construct(
        public array $billableServiceIds = [],
        public ?int $followUpInDays = null,
    ) {}
}
