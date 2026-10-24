<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDevelopmentTools();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production a relation loaded in a loop (N+1 query) fails loudly instead of slowing lists down.
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    /**
     * Telescope (requests, queries, exceptions, jobs) is a dev dependency and only runs locally — never in production.
     */
    private function registerDevelopmentTools(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}
