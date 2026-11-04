<?php

declare(strict_types=1);

namespace App\Models\Communication;

use App\Enums\Communication\ParticipantRole;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of a conversation, with their role in it and how far they have read.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $user_id
 * @property ParticipantRole $role
 * @property CarbonImmutable|null $last_read_at
 * @property CarbonImmutable|null $left_at
 * @property CarbonImmutable|null $muted_at When the member muted the chat (null = not muted)
 * @property CarbonImmutable|null $muted_until When the mute ends by itself (null = until unmuted)
 * @property-read User $user
 * @property-read Conversation $conversation
 */
class ConversationParticipant extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'user_id', 'role', 'last_read_at', 'left_at', 'muted_at', 'muted_until'];

    /** Muted at [$now]: muted, and the mute has not run out. */
    public function isMutedAt(CarbonImmutable $now): bool
    {
        return $this->muted_at !== null && ($this->muted_until === null || $this->muted_until->isAfter($now));
    }

    public function isAdmin(): bool
    {
        return $this->role === ParticipantRole::Admin;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'last_read_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
            'muted_until' => 'immutable_datetime',
        ];
    }
}
