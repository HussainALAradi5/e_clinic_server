<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Models;

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Communication\TicketCategory;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\GenericStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support request from a staff member (TKT-…). Anyone on staff opens one and follows it; admins (tickets.resolve)
 * move it through open → in progress → resolved → closed and are the only ones who resolve it.
 *
 * @property string $id
 * @property string $code
 * @property string $requester_id
 * @property string $subject
 * @property string $description
 * @property TicketCategory $category
 * @property TreatmentPriority $priority
 * @property GenericStatus $status
 * @property ?string $assigned_to
 * @property ?string $resolved_by
 * @property ?CarbonImmutable $resolved_at
 * @property ?string $resolution
 * @property int $version
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $requester
 * @property-read User|null $assignee
 * @property-read User|null $resolver
 * @property-read Collection<int, TicketComment> $comments
 */
class SupportTicket extends BaseModel implements OwnedByUser
{
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'TKT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('tickets')
            ->sortable('created_at', 'updated_at', 'code', 'status', 'priority', 'category')
            ->filterable('status', ['eq', 'in', 'nin'], enum: GenericStatus::class)
            ->filterable('priority', ['eq', 'in'], enum: TreatmentPriority::class)
            ->filterable('category', ['eq', 'in'], enum: TicketCategory::class)
            ->filterable('requester_id', ['eq'], resource: 'users', label: 'Opened by')
            ->filterable('assigned_to', ['eq', 'null'], resource: 'users', label: 'Assigned to')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('subject')
            ->column('code', 'Ticket')
            ->column('subject', 'Subject')
            ->column('category', 'Category')
            ->column('priority', 'Priority')
            ->column('status', 'Status')
            ->column('requester', 'Opened by')
            ->column('assignee', 'Assigned to')
            ->column('created_at', 'Opened')
            ->dateFields('created_at', 'resolved_at')
            ->defaultSort('-created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id')->orderBy('created_at');
    }

    /**
     * The ticket flow on the shared GenericStatus: pending (open) ⇄ in progress → completed (solved) or cancelled.
     * A solved ticket can be reopened (back to pending); a cancelled one is final.
     *
     * @return list<GenericStatus>
     */
    public static function nextStatuses(GenericStatus $from): array
    {
        return match ($from) {
            GenericStatus::Pending => [GenericStatus::InProgress, GenericStatus::Completed, GenericStatus::Cancelled],
            GenericStatus::InProgress => [GenericStatus::Pending, GenericStatus::Completed, GenericStatus::Cancelled],
            GenericStatus::Completed => [GenericStatus::Pending],
            GenericStatus::Cancelled => [],
        };
    }

    public function canMoveTo(GenericStatus $to): bool
    {
        return in_array($to, self::nextStatuses($this->status), true);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->requester_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('support_tickets.requester_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => TreatmentPriority::class,
            'status' => GenericStatus::class,
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
