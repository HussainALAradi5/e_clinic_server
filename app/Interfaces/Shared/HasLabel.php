<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

/**
 * Enums with translatable labels.
 */
interface HasLabel
{
    public function label(): string;
}
