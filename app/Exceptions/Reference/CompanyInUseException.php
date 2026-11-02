<?php

declare(strict_types=1);

namespace App\Exceptions\Reference;

use App\Exceptions\Shared\ConflictException;

/**
 * The company is linked to medications, batches, insured patients or expenses — archive it instead.
 */
final class CompanyInUseException extends ConflictException
{
    protected string $errorCode = 'COMPANY_IN_USE';
}
