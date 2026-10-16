<?php

declare(strict_types=1);

namespace App\Modules\Identity\Views\Models;

use App\Modules\Identity\Users\Models\User;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saved list layout (filters, sort, columns) of a user for one resource. Personal setting — hard deleted.
 *
 * @property string $id
 * @property string $user_id
 * @property string $resource
 * @property string $name
 * @property array<string, mixed> $query
 * @property list<mixed> $columns
 * @property bool $is_shared
 * @property bool $is_default
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ?User $user
 */
class SavedView extends Model implements OwnedByUser
{
    use HasListQuery;
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['columns' => '[]', 'is_shared' => false, 'is_default' => false];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('list_views')
            ->sortable('name', 'resource', 'created_at', 'updated_at')
            ->filterable('resource', ['eq', 'in'])
            ->filterable('is_shared', ['eq'], type: 'boolean')
            ->filterable('is_default', ['eq'], type: 'boolean')
            ->searchIn('name', 'resource')
            ->column('name', 'Name')
            ->column('resource', 'List')
            ->column('is_shared', 'Shared')
            ->column('is_default', 'Default')
            ->column('updated_at', 'Updated')
            ->dateFields('updated_at', 'created_at')
            ->defaultSort('resource,name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->user_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('user_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'columns' => 'array',
            'is_shared' => 'boolean',
            'is_default' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
