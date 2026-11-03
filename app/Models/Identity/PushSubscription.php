<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Web Push target of one browser (the PushSubscription the browser gave the web app).
 *
 * @property string $id
 * @property string $user_id
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property ?string $user_agent
 * @property CarbonImmutable $last_seen_at
 * @property-read User $user
 */
class PushSubscription extends Model
{
    use HasUuids;
    use MassPrunable;

    private const STALE_DAYS = 60;

    protected $guarded = ['id'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Browsers not seen for 60 days.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<', now()->subDays(self::STALE_DAYS));
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
