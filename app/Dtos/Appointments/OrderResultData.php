<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

final readonly class OrderResultData
{
    public function __construct(
        public string $report,
        public ?bool $isAbnormal = null,
        public ?string $reportedBy = null,
    ) {}
}
