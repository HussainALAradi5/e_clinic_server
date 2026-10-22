<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Data;

use Carbon\CarbonImmutable;

final readonly class FiscalYearData
{
    public function __construct(
        public string $name,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public bool $withAdjustmentPeriod = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['name'],
            CarbonImmutable::parse((string) $data['start_date'])->startOfDay(),
            CarbonImmutable::parse((string) $data['end_date'])->startOfDay(),
            (bool) ($data['with_adjustment_period'] ?? false),
        );
    }

    /** Calendar year `FY2026`. */
    public static function calendar(int $year): self
    {
        return new self("FY{$year}", CarbonImmutable::parse("{$year}-01-01"), CarbonImmutable::parse("{$year}-12-31"));
    }
}
