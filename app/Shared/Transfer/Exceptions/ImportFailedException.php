<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * At least one row is invalid, so nothing was imported. `meta.rows` lists each problem:
 * { row: 7, column: "Phone", message: "…" }.
 */
final class ImportFailedException extends BusinessValidationException
{
    protected string $errorCode = 'IMPORT_FAILED';
}
