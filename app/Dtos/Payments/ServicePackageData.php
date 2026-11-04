<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

/**
 * The terms of a package in the catalog.
 */
final readonly class ServicePackageData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $coveredServiceId,
        public int $sessions,
        public string $price,
        public int $validityDays,
        public bool $isActive,
    ) {}
}
