<?php

declare(strict_types=1);

namespace App\Exceptions\Communication;

use App\Exceptions\Shared\BusinessValidationException;

/** Resolving a ticket needs a word on what was done. */
final class TicketResolutionRequiredException extends BusinessValidationException
{
    protected string $errorCode = 'TICKET_RESOLUTION_REQUIRED';
}
