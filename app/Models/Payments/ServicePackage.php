<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Casts\MoneyCast;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A package in the catalog (PKG-000001): so many sessions of one service for a set price, valid so many days
 * after it is paid. Each package has its own price-list item (`sale_service_id`) that sells it; that item posts to
 * 2510 Unearned package revenue, which each session then moves to the service's own revenue.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property string $covered_service_id
 * @property string $sale_service_id
 * @property int $sessions
 * @property Money $price
 * @property int $validity_days
 * @property bool $is_active
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read BillableService $coveredService
 * @property-read BillableService $saleService
 */
class ServicePackage extends BaseModel
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'PKG';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('packages')
            ->sortable('name', 'code', 'price', 'sessions', 'created_at')
            ->filterable('is_active', ['eq'], type: 'boolean')
            ->filterable('covered_service_id', ['eq'])
            ->column('code', 'Code')
            ->column('name', 'Package')
            ->column('service', 'Covers')
            ->column('sessions', 'Sessions')
            ->column('price', 'Price')
            ->column('validity_days', 'Valid (days)')
            ->column('is_active', 'On sale')
            ->defaultSort('name');
    }

    /** @return BelongsTo<BillableService, $this> */
    public function coveredService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class, 'covered_service_id')->withDeleted();
    }

    /** @return BelongsTo<BillableService, $this> */
    public function saleService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class, 'sale_service_id')->withDeleted();
    }

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'sessions' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
