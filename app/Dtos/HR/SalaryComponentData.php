<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Enums\HR\SalaryComponentType;
use Carbon\CarbonImmutable;

final readonly class SalaryComponentData
{
    public function __construct(
        public SalaryComponentType $type,
        public string $name,
        public string $amount,
        public CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo = null,
        public bool $isTaxable = false,
    ) {}
}
