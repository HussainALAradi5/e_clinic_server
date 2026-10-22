<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Rooms\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This room has upcoming appointments. Move them to another room first.
 */
final class RoomInUseException extends ConflictException
{
    protected string $errorCode = 'ROOM_IN_USE';
}
