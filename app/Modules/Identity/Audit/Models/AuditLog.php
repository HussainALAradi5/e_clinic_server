<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Models;

use App\Enums\Identity\AuditAction;
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
use LogicException;

/**
 * Append-only audit entry: who did what, when, from where, with the changed (masked) values.
 *
 * @property string $id
 * @property ?string $user_id
 * @property AuditAction $action
 * @property ?string $auditable_type
 * @property ?string $auditable_id
 * @property string $module
 * @property ?array<string, mixed> $old_values
 * @property ?array<string, mixed> $new_values
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property ?string $request_id
 * @property CarbonImmutable $created_at
 * @property-read ?User $user
 */
class AuditLog extends Model implements OwnedByUser
{
    use HasListQuery;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('audit')
            ->sortable('created_at')
            ->filterable('user_id', ['eq', 'in'], resource: 'users', label: 'Changed by')
            ->filterable('module', ['eq', 'in'])
            ->filterable('action', ['eq', 'in', 'nin'], enum: AuditAction::class)
            ->filterable('auditable_type', ['eq'], label: 'Record type')
            ->filterable('auditable_id', ['eq'], hidden: true)
            ->filterable('request_id', ['eq'], hidden: true)
            ->column('created_at', 'Time')
            ->column('user', 'User')
            ->column('action', 'Action')
            ->column('module', 'Module')
            ->column('auditable', 'Record')
            ->dateFields('created_at')
            ->maxDateRangeDays((int) config('audit.max_range_days', 93))
            ->cursorPagination()
            ->defaultSort('-id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Public alias of the audited model (config audit.models), e.g. `role`. */
    public function auditableAlias(): ?string
    {
        $alias = array_search($this->auditable_type, (array) config('audit.models'), true);

        return is_string($alias) ? $alias : null;
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

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Audit logs are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Audit logs are append-only.');
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
