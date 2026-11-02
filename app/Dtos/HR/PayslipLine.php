<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Enums\HR\PayslipItemType;
use Brick\Money\Money;

/**
 * One computed payslip line (positive amount).
 */
final readonly class PayslipLine
{
    public function __construct(public PayslipItemType $type, public string $name, public Money $amount) {}
}
