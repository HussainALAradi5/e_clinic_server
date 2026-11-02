<?php

declare(strict_types=1);

namespace App\Models\Communication;

use App\Enums\Communication\MessageKind;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Identity\Attachment;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One chat message: text, photos / files, or a voice note (its files are attachments of the message), with the
 * members it @mentions. Only current members of its conversation may read it or its files.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_id
 * @property ?string $body
 * @property MessageKind $kind
 * @property ?list<string> $mentions
 * @property CarbonImmutable $created_at
 * @property-read User $sender
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, MessageReaction> $reactions
 */
class Message extends Model implements OwnedByUser
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'sender_id', 'reply_to_id', 'body', 'kind', 'mentions'];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => 'text'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The message this one answers (same chat), shown as a quote.
     *
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return static::query()->ownedBy($user)->whereKey($this->id)->exists();
    }

    /**
     * Messages of the chats the user is a current member of (so files follow the chat, never leak to another).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->whereIn('messages.conversation_id', ConversationParticipant::query()
            ->select('conversation_id')->where('user_id', $user->getAuthIdentifier())->whereNull('left_at'));
    }

    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'mentions' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
