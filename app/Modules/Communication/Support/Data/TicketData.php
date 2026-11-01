<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Data;

use App\Enums\Communication\TicketCategory;
use App\Enums\Communication\TicketPriority;

/** What a staff member reports. */
final readonly class TicketData
{
    public function __construct(
        public string $subject,
        public string $description,
        public TicketCategory $category,
        public TicketPriority $priority,
    ) {}
}
