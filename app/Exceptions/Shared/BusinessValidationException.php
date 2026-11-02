<?php

declare(strict_types=1);

namespace App\Exceptions\Shared;

/**
 * A business rule rejected otherwise valid input (422).
 */
abstract class BusinessValidationException extends DomainException
{
    protected int $status = 422;

    protected string $errorCode = 'VALIDATION_FAILED';
}
