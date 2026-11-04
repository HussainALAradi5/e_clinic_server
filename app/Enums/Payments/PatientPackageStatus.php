<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * A package a patient bought: awaiting payment (its invoice is not paid yet), active (sessions can be used),
 * used up, expired (unused sessions forfeited) or cancelled (its invoice was cancelled before payment).
 */
enum PatientPackageStatus: string implements HasLabel
{
    use EnumHelpers;

    case AwaitingPayment = 'awaiting_payment';
    case Active = 'active';
    case UsedUp = 'used_up';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
