<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\NotificationStatus;
use App\Interfaces\Shared\OwnedByUser;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\FilterOptions;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbox + delivery log: one row per recipient per channel (queued → sent → delivered | failed | cancelled).
 *
 * @property string $id
 * @property string $person_id
 * @property ?string $user_id
 * @property NotificationChannel $channel
 * @property string $template
 * @property string $locale
 * @property ?string $destination
 * @property ?string $subject
 * @property string $body
 * @property ?string $related_type
 * @property ?string $related_id
 * @property NotificationStatus $status
 * @property CarbonImmutable $scheduled_for
 * @property int $attempts
 * @property ?string $last_error
 * @property ?string $provider_message_id
 * @property ?CarbonImmutable $sent_at
 * @property ?CarbonImmutable $delivered_at
 * @property ?CarbonImmutable $read_at
 * @property CarbonImmutable $created_at
 * @property-read Person $person
 * @property-read ?User $user
 */
class Notification extends Model implements OwnedByUser
{
    use HasListQuery;
    use HasUuids;
    use MassPrunable;

    public const MAX_ATTEMPTS = 3;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued', 'attempts' => 0, 'locale' => 'en'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('notifications')
            ->sortable('created_at', 'scheduled_for', 'sent_at', 'status', 'channel', 'template')
            ->searchIn('subject')
            ->filterable('person_id', ['eq'], resource: 'people', label: 'Recipient')
            ->filterable('channel', ['eq', 'in'], enum: NotificationChannel::class, label: 'Sent via')
            ->filterable('status', ['eq', 'in'], enum: NotificationStatus::class)
            ->filterable('template', ['eq', 'like'], label: 'Message')
            ->filterable('related_type', ['eq', 'in'], label: 'About', options: FilterOptions::inUse(self::class, 'related_type', FilterOptions::inWords()))
            ->filterable('related_id', ['eq'], hidden: true)
            ->column('scheduled_for', 'Scheduled')
            ->column('channel', 'Sent via')
            ->column('template', 'Message')
            ->column('destination', 'Sent to')
            ->column('status', 'Status')
            ->column('attempts', 'Tries')
            ->column('sent_at', 'Sent')
            ->dateFields('created_at', 'scheduled_for', 'sent_at')
            ->defaultSort('-created_at');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
        return $this->person_id === $user->getAuthIdentifier() || $this->user_id === $user->getAuthIdentifier();
    }

    /**
     * Own log: the person record shares the user's id (class-table inheritance).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('person_id', $user->getAuthIdentifier())->orWhere('user_id', $user->getAuthIdentifier()));
    }

    /**
     * In-app rows after 6 months, everything after 2 years.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $q) => $q
            ->where('created_at', '<', now()->subYears(2))
            ->orWhere(fn (Builder $in) => $in->where('channel', NotificationChannel::InApp)->where('created_at', '<', now()->subMonths(6))));
    }

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
