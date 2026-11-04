<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;
use Illuminate\Support\Facades\Config;

/**
 * Why a patient is charged for a visit that did not happen.
 */
enum AppointmentFeeType: string implements HasLabel
{
    use EnumHelpers;

    /** Did not come and did not cancel. */
    case NoShow = 'no_show';

    /** The patient cancelled within `payments.fees.late_cancel_hours` of the start. */
    case LateCancel = 'late_cancel';

    /** Price-list code of the fee (the price list holds its amount; archive it or set 0 to stop charging). */
    public function serviceCode(): string
    {
        return Config::string('payments.fees.services.'.$this->value.'.code');
    }
}
