<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

/**
 * Exceptions rendered as the standard API error envelope.
 */
interface RendersApiError
{
    public function httpStatus(): int;

    public function errorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}
