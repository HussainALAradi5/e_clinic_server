<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Data;

use App\Shared\Enums\PaymentMethod;
use Carbon\CarbonImmutable;

final readonly class PayExpenseData
{
    public function __construct(
        public CarbonImmutable $paidAt,
        public PaymentMethod $method,
        public ?string $reference,
    ) {}
}
