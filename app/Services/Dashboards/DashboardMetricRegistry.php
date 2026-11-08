<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Dtos\Dashboards\DashboardMetric;
use App\Enums\Dashboards\DashboardSection;
use App\Interfaces\Dashboards\DashboardMetricProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Knows every section's metrics, from the providers registered at boot. Providers are built on first use, so a request
 * that never touches a dashboard pays nothing.
 */
final class DashboardMetricRegistry
{
    /** @var list<class-string<DashboardMetricProvider>> */
    private array $providers = [];

    /** @var array<string, array<string, DashboardMetric>>|null section => metric key => metric */
    private ?array $catalog = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<DashboardMetricProvider>  $provider
     */
    public function register(string $provider): void
    {
        $this->providers[] = $provider;
        $this->catalog = null;
    }

    /**
     * @return list<DashboardMetric>
     */
    public function metrics(DashboardSection $section): array
    {
        return array_values($this->catalog()[$section->value] ?? []);
    }

    public function find(DashboardSection $section, string $key): ?DashboardMetric
    {
        return $this->catalog()[$section->value][$key] ?? null;
    }

    /**
     * @return array<string, array<string, DashboardMetric>>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        $catalog = [];
        foreach ($this->providers as $class) {
            /** @var DashboardMetricProvider $provider */
            $provider = $this->container->make($class);
            foreach ($provider->metrics() as $metric) {
                $catalog[$provider->section()->value][$metric->key] = $metric;
            }
        }

        return $this->catalog = $catalog;
    }
}
