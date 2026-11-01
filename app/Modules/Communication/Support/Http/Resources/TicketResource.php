<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Http\Resources;

use App\Enums\Communication\TicketStatus;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Identity\Users\Models\User;
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
            'allowed_transitions' => array_map(fn (TicketStatus $s): string => $s->value, TicketStatus::transitions()[$this->status->value]),
            'requester' => $this->whenLoaded('requester', fn (): ?array => $person($this->requester)),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $person($this->assignee)),
            'resolver' => $this->whenLoaded('resolver', fn (): ?array => $person($this->resolver)),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution' => $this->resolution,
            'comments_count' => $this->whenCounted('comments'),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
