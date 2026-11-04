<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * An online payment: waiting for the payer on the gateway's page, captured (money booked on the invoice), failed /
 * declined, or expired (abandoned past `payments.online.expiry_minutes`).
 */
enum OnlinePaymentStatus: string implements HasLabel
{
    use EnumHelpers;

    case Initiated = 'initiated';
    case Captured = 'captured';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isFinal(): bool
    {
        return $this !== self::Initiated;
    }
}
