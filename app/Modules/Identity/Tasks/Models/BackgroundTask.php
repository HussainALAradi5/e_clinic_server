<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Models;

use App\Enums\Identity\TaskCancelReason;
use App\Enums\Identity\TaskType;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Contracts\HasStateMachine;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\GenericStatus;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasGenericStatus;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One long-running operation (export, ZIP, bulk action …): pending → in_progress → completed / cancelled.
 *
 * @property string $id
 * @property TaskType $type
 * @property GenericStatus $status
 * @property int $progress
 * @property array<string, mixed> $parameters
 * @property ?string $subject_type
 * @property ?string $subject_id
 * @property ?string $result_path
 * @property ?array<string, mixed> $result_summary
 * @property ?TaskCancelReason $cancel_reason
 * @property ?string $error_message
 * @property string $requested_by
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property ?CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ?User $requester
 */
class BackgroundTask extends Model implements HasStateMachine, OwnedByUser
{
    use HasGenericStatus;
    use HasListQuery;
    use HasUuids;
    use MassPrunable;

    private const RETENTION_DAYS = 90;

    protected $guarded = ['id'];

    protected $hidden = ['result_path'];

    protected $attributes = ['progress' => 0, 'parameters' => '{}'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('background_tasks')
            ->sortable('created_at', 'finished_at', 'type', 'status', 'progress')
            ->filterable('type', ['eq', 'in'], enum: TaskType::class)
            ->filterable('status', ['eq', 'in'], enum: GenericStatus::class)
            ->column('created_at', 'Started')
            ->column('type', 'Task')
            ->column('status', 'Status')
            ->column('progress', 'Progress')
            ->column('finished_at', 'Finished')
            ->dateFields('created_at', 'finished_at')
            ->defaultSort('-created_at');
    }

    /** The cancellation reason is an enum column set by the service, not free text. */
    public function cancellationReasonColumn(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->requested_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('requested_by', $user->getAuthIdentifier());
    }

    /**
     * Finished tasks older than 90 days (their files are purged after expires_at by tasks:purge-results).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereIn('status', [GenericStatus::Completed, GenericStatus::Cancelled])
            ->where('finished_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'progress' => 'integer',
            'parameters' => 'array',
            'result_summary' => 'array',
            'cancel_reason' => TaskCancelReason::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
