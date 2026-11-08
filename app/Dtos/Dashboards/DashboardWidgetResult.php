<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

use App\Enums\Dashboards\DashboardMetricKind;

/** One widget with its figure (`stat`) or rows (`list`). */
final readonly class DashboardWidgetResult
{
    /**
     * @param  ?list<array{id: string, title: string, subtitle: ?string, value: string}>  $items
     */
    public function __construct(
        public string $metric,
        public string $title,
        public DashboardMetricKind $kind,
        public string $size,
        public int $position,
        public ?int $value,
        public ?array $items,
    ) {}
}
