<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Resources;

use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Communication\Chat\Models\MessageReaction;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Support\Avatar;
use App\Shared\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A message as every member sees it (the same payload goes over the socket, so nothing here depends on who asks):
 * the sender with their photo and group role, the quoted message it answers, files with short-lived links, and
 * reactions grouped by emoji.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /** How long a file link in a loaded chat stays valid. */
    private const LINK_MINUTES = 60;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'kind' => $this->kind->value,
            'body' => $this->body,
            'mentions' => $this->mentions ?? [],
            'sender' => [
                'id' => $this->sender_id,
                'full_name' => $this->sender->person->full_name,
                'avatar_url' => Avatar::url($this->sender->person),
                // The sender's role in a group (admin = leader); null in a one-to-one chat.
                'role' => $this->getAttribute('sender_role'),
            ],
            // The quote: who wrote it and a short text (or what kind of file it was); null when it is not a reply
            // or the quoted message was deleted.
            'reply_to' => $this->whenLoaded('replyTo', fn (): ?array => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'sender_name' => $this->replyTo->sender->person->full_name,
                'kind' => $this->replyTo->kind->value,
                'excerpt' => mb_strimwidth((string) $this->replyTo->body, 0, 120, '…'),
                'file_name' => $this->replyTo->attachments->first()?->original_name,
            ], null),
            'attachments' => $this->whenLoaded('attachments', fn (): array => $this->attachments->map(fn (Attachment $a): array => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => $a->size_bytes,
                // No link until the file passed the scan.
                'url' => $a->isDownloadable() ? app(FileStorage::class)->temporaryUrl($a->path, $a->original_name, self::LINK_MINUTES, inline: true)['url'] : null,
            ])->values()->all(), []),
            'reactions' => $this->whenLoaded('reactions', fn (): array => $this->reactions
                ->groupBy('emoji')
                ->map(fn ($group, string $emoji): array => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'user_ids' => $group->pluck('user_id')->values()->all(),
                    'names' => $group->map(fn (MessageReaction $r): string => $r->user->person->full_name)->values()->all(),
                ])->values()->all(), []),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
