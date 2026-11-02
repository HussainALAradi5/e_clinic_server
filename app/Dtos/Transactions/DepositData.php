<?php

declare(strict_types=1);

namespace App\Dtos\Transactions;

use Carbon\CarbonImmutable;

final readonly class DepositData
{
    public function __construct(
        public CarbonImmutable $date,
        public string $reference,
        public string $amount,
    ) {}
}
