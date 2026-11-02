<?php

declare(strict_types=1);

namespace App\Dtos\Communication;

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Communication\TicketCategory;

/** What a staff member reports. */
final readonly class TicketData
{
    public function __construct(
        public string $subject,
        public string $description,
        public TicketCategory $category,
        public TreatmentPriority $priority,
    ) {}
}
