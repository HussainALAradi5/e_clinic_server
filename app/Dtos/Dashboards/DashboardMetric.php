<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

use App\Enums\Dashboards\DashboardMetricKind;
use Closure;

/**
 * One figure a section can show. `access` is a permission expression (`attendance.read_all&attendance.approve_overtime`)
 * checked for each signed-in user; the resolver receives the filter and returns the figure or the list rows.
 */
final readonly class DashboardMetric
{
    /**
     * @param  Closure(DashboardFilterDto): (int|list<array{id: string, title: string, subtitle: ?string, value: string}>)  $resolver
     */
    public function __construct(
        public string $key,
        public string $label,
        public DashboardMetricKind $kind,
        public string $access,
        public Closure $resolver,
    ) {}

    /**
     * @return int|list<array{id: string, title: string, subtitle: ?string, value: string}>
     */
    public function resolve(DashboardFilterDto $filter): int|array
    {
        return ($this->resolver)($filter);
    }
}
