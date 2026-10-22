<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Rooms\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This room is out of service, archived, of the wrong type or already booked at that time.
 */
final class RoomUnavailableException extends ConflictException
{
    protected string $errorCode = 'ROOM_UNAVAILABLE';
}
