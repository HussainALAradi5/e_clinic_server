<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This room has upcoming appointments. Move them to another room first.
 */
final class RoomInUseException extends ConflictException
{
    protected string $errorCode = 'ROOM_IN_USE';
}
