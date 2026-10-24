<?php

declare(strict_types=1);

namespace App\Modules\Payments\Catalog\Models;

use App\Enums\Appointments\ConsentType;
use App\Enums\Payments\ServiceCategory;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price-list item (the single source of prices). Codes are entered by billing staff, e.g. `CONS-GP`.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $name_ar
 * @property ServiceCategory $category
 * @property Money $price
 * @property string $tax_rate
 * @property string $revenue_account_id
 * @property ?string $specialty_id
 * @property bool $is_insurable
 * @property ?ConsentType $consent_type
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Account $revenueAccount
 * @property-read ?Specialty $specialty
 */
class BillableService extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'SRV';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('services')
            ->sortable('code', 'name', 'category', 'price', 'created_at')
            ->filterable('category', ['eq', 'in'], enum: ServiceCategory::class)
            ->filterable('specialty_id', ['eq', 'in', 'null'], resource: 'specialties')
            ->filterable('price', ['gte', 'lte', 'between'], type: 'money')
            ->filterable('is_insurable', ['eq'], type: 'boolean')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->includes('specialty', 'revenueAccount')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('category', 'Category')
            ->column('price', 'Price')
            ->column('tax_rate', 'VAT %')
            ->column('specialty', 'Specialty')
            ->column('is_insurable', 'Covered by insurance')
            ->dateFields('created_at')
            ->defaultSort('code');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id')->withDeleted();
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'consent_type' => ConsentType::class,
            'price' => MoneyCast::class,
            'tax_rate' => 'decimal:2',
            'is_insurable' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
