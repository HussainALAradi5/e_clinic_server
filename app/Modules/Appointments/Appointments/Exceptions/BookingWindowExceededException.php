<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Appointments can be booked up to 90 days ahead.
 */
final class BookingWindowExceededException extends BusinessValidationException
{
    protected string $errorCode = 'BOOKING_WINDOW_EXCEEDED';
}
