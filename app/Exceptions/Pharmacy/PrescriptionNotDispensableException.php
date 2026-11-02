<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ConflictException;

/**
 * The prescription is cancelled, completed or past its `valid_until` date.
 */
final class PrescriptionNotDispensableException extends ConflictException
{
    protected string $errorCode = 'PRESCRIPTION_NOT_DISPENSABLE';
}
