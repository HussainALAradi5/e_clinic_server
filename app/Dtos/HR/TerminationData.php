<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Enums\HR\TerminationReason;
use Carbon\CarbonImmutable;

final readonly class TerminationData
{
    public function __construct(
        public CarbonImmutable $date,
        public TerminationReason $reason,
        public ?string $notes = null,
    ) {}
}
