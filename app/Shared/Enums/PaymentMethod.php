<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * How money was received or paid.
 */
enum PaymentMethod: string implements HasLabel
{
    use EnumHelpers;

    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case BenefitPay = 'benefit_pay';
    case Insurance = 'insurance';
    case Cheque = 'cheque';
}
