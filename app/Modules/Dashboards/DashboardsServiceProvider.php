<?php

declare(strict_types=1);

namespace App\Modules\Dashboards;

use App\Interfaces\Dashboards\DashboardServiceInterface;
use App\Services\Dashboards\DashboardMetricRegistry;
use App\Services\Dashboards\DashboardService;
use App\Services\Dashboards\Providers\AttendanceMetricProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Dashboards module: saved dashboards of any section. Add a section's metric provider to METRIC_PROVIDERS.
 */
final class DashboardsServiceProvider extends ServiceProvider
{
    private const METRIC_PROVIDERS = [
        AttendanceMetricProvider::class,
    ];

    /** @var array<class-string, class-string> */
    public array $singletons = [
        DashboardMetricRegistry::class => DashboardMetricRegistry::class,
        DashboardServiceInterface::class => DashboardService::class,
    ];

    public function boot(DashboardMetricRegistry $registry): void
    {
        foreach (self::METRIC_PROVIDERS as $provider) {
            $registry->register($provider);
        }
    }
}
