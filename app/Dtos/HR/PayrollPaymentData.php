<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use Carbon\CarbonImmutable;

/**
 * Salaries transferred to the staff (WPS / bank file reference).
 */
final readonly class PayrollPaymentData
{
    public function __construct(public CarbonImmutable $paidOn, public ?string $reference = null) {}
}
