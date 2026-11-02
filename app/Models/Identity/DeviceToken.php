<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\DevicePlatform;
use App\Enums\Identity\MobileApp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Push target of a Flutter app installation (FCM registration token).
 *
 * @property string $id
 * @property string $user_id
 * @property MobileApp $app
 * @property DevicePlatform $platform
 * @property string $token
 * @property ?string $device_name
 * @property ?string $app_version
 * @property CarbonImmutable $last_seen_at
 * @property-read User $user
 */
class DeviceToken extends Model
{
    use HasUuids;
    use MassPrunable;

    private const STALE_DAYS = 60;

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tokens not seen for 60 days.
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
            'app' => MobileApp::class,
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
