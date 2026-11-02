<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This time is outside working hours, in the past or already booked.
 */
final class SlotUnavailableException extends ConflictException
{
    protected string $errorCode = 'SLOT_UNAVAILABLE';
}
