<?php

declare(strict_types=1);

namespace App\Http\Resources\Communication;

use App\Models\Communication\Conversation;
use App\Models\Communication\ConversationParticipant;
use App\Utilities\Identity\Avatar;
use Carbon\CarbonImmutable;
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
        $leftAt = $this->getAttribute('my_left_at');
        $last = $this->latestMessage;
        if ($last !== null && $leftAt !== null && $last->created_at > CarbonImmutable::parse($leftAt)) {
            $last = null;
        }

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->isGroup() ? $this->title : ($other?->user->person->full_name ?? (string) $this->title),
            'partner_id' => $other?->user_id,
            'partner_presence' => $other?->user->presence->value,
            'avatar_url' => $other === null ? null : Avatar::url($other->user->person),
            'my_role' => $this->participantFor($me)?->role->value,
            'muted' => $this->participantFor($me)?->isMutedAt(CarbonImmutable::now()) ?? false,
            'muted_until' => $this->participantFor($me)?->muted_until?->toIso8601String(),
            'members' => ParticipantResource::collection($this->participants),
            'last_message' => $last === null ? null : new MessageResource($last),
            'left' => $leftAt !== null,
            'unread_count' => (int) ($this->getAttribute('unread_count') ?? 0),
            'messages_count' => $this->when($this->getAttribute('messages_count') !== null, fn (): int => (int) $this->getAttribute('messages_count')),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'deleted_by' => $this->deleted_at === null ? null : $this->deleter?->person->full_name,
        ];
    }
}
