<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Data;

use Carbon\CarbonImmutable;

final readonly class DepositData
{
    public function __construct(
        public CarbonImmutable $date,
        public string $reference,
        public string $amount,
    ) {}
}
