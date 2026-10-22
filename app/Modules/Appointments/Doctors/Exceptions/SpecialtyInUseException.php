<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This specialty is assigned to doctors or services. Archive it instead.
 */
final class SpecialtyInUseException extends ConflictException
{
    protected string $errorCode = 'SPECIALTY_IN_USE';
}
