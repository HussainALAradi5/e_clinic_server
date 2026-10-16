<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Shared\Contracts\RendersApiError;
use RuntimeException;

/**
 * Base of every expected business error. Rendered as the standard error envelope by ApiExceptionRenderer.
 */
abstract class DomainException extends RuntimeException implements RendersApiError
{
    protected int $status = 400;

    protected string $errorCode = 'DOMAIN_ERROR';

    /**
     * @param  array<string, mixed>  $context  `errors` (field => messages) is returned as `errors`, the rest as `meta`
     */
    public function __construct(?string $message = null, protected array $context = [])
    {
        parent::__construct($message ?? __('errors.'.$this->errorCode));
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
