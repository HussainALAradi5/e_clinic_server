<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Models;

use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's emoji on a message (one each; reacting again replaces it, the same emoji removes it).
 *
 * @property string $id
 * @property string $message_id
 * @property string $user_id
 * @property string $emoji
 * @property-read User $user
 */
class MessageReaction extends Model
{
    use HasUuids;

    /** The quick row offered on a message (as on WhatsApp). */
    public const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /** Everything behind "+": the quick row first, then more faces, hands and work signs. */
    public const ALL_EMOJIS = [
        ...self::EMOJIS,
        '😀', '😊', '🥰', '😍', '😎', '🤔', '😅', '🙂', '😉', '🤗', '😴', '😬', '😡', '🥲', '😭', '🤩',
        '👏', '🙌', '👌', '✌️', '🤝', '💪', '👀', '👎', '🫡', '🤞',
        '🔥', '✨', '🎉', '💯', '✅', '❌', '⚠️', '⭐', '💡', '📌', '⏰', '☕', '🩺', '💊', '🚑', '❤️‍🩹',
    ];

    /** @var list<string> */
    protected $fillable = ['message_id', 'user_id', 'emoji'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
