<?php

declare(strict_types=1);

namespace App\Modules\Identity\Codes\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

final class InvalidScanCodeException extends BusinessValidationException
{
    protected string $errorCode = 'SCAN_CODE_INVALID';
}
