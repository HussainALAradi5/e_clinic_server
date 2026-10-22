<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Too many rows for the chosen format (PDF is limited; Excel / CSV are not).
 */
final class ExportTooLargeException extends BusinessValidationException
{
    protected string $errorCode = 'EXPORT_TOO_LARGE';
}
