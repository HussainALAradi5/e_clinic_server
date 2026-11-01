<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Resources;

use App\Modules\Communication\Chat\Models\Conversation;
use App\Modules\Communication\Chat\Models\ConversationParticipant;
use App\Modules\Identity\Users\Support\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A chat as the signed-in user sees it: a direct chat is named after the other person; `unread_count` and `my_role`
 * are theirs.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $other = $this->isGroup() ? null : $this->participants->first(fn (ConversationParticipant $p): bool => $p->user_id !== $me);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->isGroup() ? $this->title : ($other?->user->person->full_name ?? (string) $this->title),
            'partner_id' => $other?->user_id,
            'avatar_url' => $other === null ? null : Avatar::url($other->user->person),
            'my_role' => $this->participantFor($me)?->role->value,
            'members' => ParticipantResource::collection($this->participants),
            'last_message' => $this->latestMessage === null ? null : new MessageResource($this->latestMessage),
            'unread_count' => (int) ($this->getAttribute('unread_count') ?? 0),
            'messages_count' => $this->when($this->getAttribute('messages_count') !== null, fn (): int => (int) $this->getAttribute('messages_count')),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
