<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The company is linked to medications, batches, insured patients or expenses — archive it instead.
 */
final class CompanyInUseException extends ConflictException
{
    protected string $errorCode = 'COMPANY_IN_USE';
}
