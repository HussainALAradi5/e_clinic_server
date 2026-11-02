<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Medicines given in a session need a checked-in / in-progress appointment of the same patient.
 */
final class AdministrationRequiresSessionException extends BusinessValidationException
{
    protected string $errorCode = 'ADMINISTRATION_REQUIRES_SESSION';
}
