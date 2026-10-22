<?php

declare(strict_types=1);

namespace App\Shared\Data;

/**
 * One billable item of a Chargeable: a price-list service, its quantity, an optional printed description and the
 * doctor credited with the revenue.
 */
final readonly class ChargeLine
{
    public function __construct(
        public string $billableServiceId,
        public string $quantity = '1',
        public ?string $description = null,
        public ?string $doctorId = null,
    ) {}
}
