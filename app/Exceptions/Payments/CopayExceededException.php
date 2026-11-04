<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The patient's insurer covers part of this invoice: the desk takes only the patient's share (the co-pay), unless
 * the patient chooses to pay the insurer's share too (`pay_insurer_share`).
 */
final class CopayExceededException extends BusinessValidationException
{
    protected string $errorCode = 'COPAY_EXCEEDED';
}
