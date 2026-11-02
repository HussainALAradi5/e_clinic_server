<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This room is out of service, archived, of the wrong type or already booked at that time.
 */
final class RoomUnavailableException extends ConflictException
{
    protected string $errorCode = 'ROOM_UNAVAILABLE';
}
