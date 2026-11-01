<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Http\Resources;

use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Enums\GenericStatus;
use App\Shared\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A support ticket; `allowed_transitions` drive the admin buttons (the server checks again), `comments` come with a
 * single ticket.
 *
 * @mixin SupportTicket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn (?User $user): ?array => $user === null ? null : ['id' => $user->id, 'full_name' => $user->person->full_name];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'subject' => $this->subject,
            'description' => $this->description,
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn (GenericStatus $s): string => $s->value, SupportTicket::nextStatuses($this->status)),
            'requester' => $this->whenLoaded('requester', fn (): ?array => $person($this->requester)),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $person($this->assignee)),
            'resolver' => $this->whenLoaded('resolver', fn (): ?array => $person($this->resolver)),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution' => $this->resolution,
            'resolution_steps' => $this->resolution_steps ?? [],
            'confirmation' => $this->confirmation?->value,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'attachments' => $this->whenLoaded('attachments', fn (): array => $this->attachments->map(fn (Attachment $a): array => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => $a->size_bytes,
                'scan_status' => $a->scan_status->value,
                // No link until the file passed the scan.
                'url' => $a->isDownloadable() ? app(FileStorage::class)->temporaryUrl($a->path, $a->original_name, 60, inline: true)['url'] : null,
            ])->values()->all(), []),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
