<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Enums with translatable labels.
 */
interface HasLabel
{
    public function label(): string;
}
