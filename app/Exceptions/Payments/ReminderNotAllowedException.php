<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * No reminder now: the patient owes nothing of their own on the invoice, or one went out too recently.
 */
final class ReminderNotAllowedException extends ConflictException
{
    protected string $errorCode = 'REMINDER_NOT_ALLOWED';
}
