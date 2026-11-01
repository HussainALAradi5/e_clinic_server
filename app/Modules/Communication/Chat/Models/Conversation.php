<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Models;

use App\Enums\Communication\ConversationType;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A staff chat: two colleagues (direct) or a named group.
 *
 * @property string $id
 * @property ConversationType $type
 * @property string|null $title
 * @property string|null $direct_key
 * @property string $created_by
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $deleted_at
 * @property string|null $deleted_by
 * @property-read User|null $deleter
 * @property-read Collection<int, ConversationParticipant> $participants
 * @property-read Message|null $latestMessage
 */
class Conversation extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['type', 'title', 'direct_key', 'created_by', 'last_message_at', 'deleted_at', 'deleted_by'];

    /** The key that makes a direct chat between two people unique, whoever starts it. */
    public static function directKey(string $a, string $b): string
    {
        $pair = [$a, $b];
        sort($pair);

        return implode(':', $pair);
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    /**
     * Current members (people who left stay as history but are not listed).
     *
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class)->whereNull('left_at');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Who deleted the chat (it is only ever stamped, never removed).
     *
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participantFor(string $userId): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $userId);
    }

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
