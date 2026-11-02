<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

final class InvalidScanCodeException extends BusinessValidationException
{
    protected string $errorCode = 'SCAN_CODE_INVALID';
}
