<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\BalanceSide;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chart-of-accounts node: a group (non-postable) or a leaf account. Codes are entered by the accountant (e.g. `4100`).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $name_ar
 * @property AccountType $type
 * @property BalanceSide $normal_balance
 * @property ?string $parent_id
 * @property bool $is_group
 * @property bool $is_system
 * @property int $level
 * @property ?string $description
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Account $parent
 */
class Account extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'ACC';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('accounts')
            ->sortable('code', 'name', 'type', 'level', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: AccountType::class)
            ->filterable('is_group', ['eq'], type: 'boolean')
            ->filterable('parent_id', ['eq', 'null'], resource: 'accounts')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->includes('parent')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('type', 'Type')
            ->column('normal_balance', 'Normal balance')
            ->column('parent', 'Parent')
            ->column('is_group', 'Group')
            ->dateFields('created_at')
            ->defaultSort('code');
    }

    /** True when journal lines may post to it. */
    public function isPostable(): bool
    {
        return ! $this->is_group && ! $this->is_archived && ! $this->is_deleted;
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
            'type' => AccountType::class,
            'normal_balance' => BalanceSide::class,
            'is_group' => 'boolean',
            'is_system' => 'boolean',
            'level' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
