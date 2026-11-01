<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Models;

use App\Enums\Communication\ParticipantRole;
use App\Modules\Identity\Users\Models\User;
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
 * @property-read User $user
 * @property-read Conversation $conversation
 */
class ConversationParticipant extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'user_id', 'role', 'last_read_at', 'left_at'];

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
        ];
    }
}
