<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Shared\BulkActionController;
use App\Http\Controllers\Shared\TransferController;
use App\Services\Bulk\BulkActionRegistry;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\DocumentNumberGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Shared kernel: singletons, rate-limit tiers, migration macros and the bulk route macro.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClinicClock::class);
        $this->app->singleton(DocumentNumberGenerator::class);
        $this->app->singleton(BulkActionRegistry::class);
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerBlueprintMacros();

        /** `Route::bulkActions('clinic/rooms', 'rooms')` → POST clinic/rooms/bulk. */
        Route::macro('bulkActions', fn (string $uri, string $resource) => Route::post(trim($uri, '/').'/bulk', BulkActionController::class)
            ->defaults('resource', $resource)
            ->middleware('throttle:sensitive')
            ->name($resource.'.bulk'));

        /**
         * `Route::dataTransfer('reference/companies', 'companies')` → POST …/export, GET …/import/template,
         * POST …/import. Permission checks (`<resource>.export|import`, never patients) live in TransferGuard.
         */
        Route::macro('dataTransfer', function (string $uri, string $resource): void {
            $uri = trim($uri, '/');
            Route::post($uri.'/export', [TransferController::class, 'export'])->defaults('resource', $resource)->middleware('throttle:report')->name($resource.'.export');
            Route::get($uri.'/import/template', [TransferController::class, 'template'])->defaults('resource', $resource)->middleware('throttle:report')->name($resource.'.import-template');
            Route::post($uri.'/import', [TransferController::class, 'import'])->defaults('resource', $resource)->middleware('throttle:sensitive')->name($resource.'.import');
        });
    }

    private function registerRateLimiters(): void
    {
        $byUser = fn (Request $r): string => (string) ($r->user()?->getAuthIdentifier() ?? $r->ip());

        RateLimiter::for('auth', fn (Request $r): array => [
            Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by((string) $r->ip()),
        ]);
        RateLimiter::for('sensitive', fn (Request $r): Limit => Limit::perMinute(10)->by($byUser($r)));
        RateLimiter::for('write', fn (Request $r): Limit => Limit::perMinute(60)->by($byUser($r)));
        RateLimiter::for('read', fn (Request $r): Limit => Limit::perMinute(120)->by($byUser($r)));
        RateLimiter::for('report', fn (Request $r): Limit => Limit::perMinute(10)->by($byUser($r)));
        RateLimiter::for('upload', fn (Request $r): Limit => Limit::perMinute(600)->by($byUser($r)));
        RateLimiter::for('public', fn (Request $r): Limit => Limit::perMinute(30)->by((string) $r->ip()));
    }

    private function registerBlueprintMacros(): void
    {
        /** GenericEntity columns: uuid id, code, timestamps, is_archived, is_deleted, deleted_at, deleted_by. */
        Blueprint::macro('genericEntity', function (int $codeLength = 30): void {
            /** @var Blueprint $this */
            // Explicit primary-key command (not the fluent ->primary()) so it is created before any foreign key of
            // the same table — self-references (accounts.parent_id) need it on PostgreSQL.
            $this->uuid('id');
            $this->primary('id');
            $this->string('code', $codeLength)->unique();
            $this->timestampsTz();
            $this->boolean('is_archived')->default(false);
            $this->boolean('is_deleted')->default(false);
            $this->timestampTz('deleted_at')->nullable();
            $this->uuid('deleted_by')->nullable();
            $this->index(['is_deleted', 'is_archived']);
        });

        /** created_by / updated_by (user ids). */
        Blueprint::macro('blameable', function (): void {
            /** @var Blueprint $this */
            $this->uuid('created_by')->nullable();
            $this->uuid('updated_by')->nullable();
        });

        /** Optimistic lock column used by HasVersion. */
        Blueprint::macro('version', function (): void {
            /** @var Blueprint $this */
            $this->unsignedInteger('version')->default(1);
        });

        /** numeric(14,3) money column (MoneyCast). */
        Blueprint::macro('money', function (string $column): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->decimal($column, 14, 3);
        });
    }
}
