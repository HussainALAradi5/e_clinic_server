<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\LoginFailureReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One login attempt (success or failure) for lockout review. Pruned after 180 days (`model:prune`).
 *
 * @property string $id
 * @property ?string $user_id
 * @property string $email
 * @property string $ip_address
 * @property ?string $user_agent
 * @property bool $successful
 * @property ?LoginFailureReason $failure_reason
 * @property CarbonImmutable $created_at
 */
class LoginAttempt extends Model
{
    use HasUuids;
    use MassPrunable;

    public const UPDATED_AT = null;

    private const RETENTION_DAYS = 180;

    protected $guarded = ['id'];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'failure_reason' => LoginFailureReason::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
