<?php

declare(strict_types=1);

namespace App\Exceptions\Transfer;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The upload is not a readable workbook, misses required columns, is empty or has too many rows.
 */
final class InvalidImportFileException extends BusinessValidationException
{
    protected string $errorCode = 'IMPORT_FILE_INVALID';
}
