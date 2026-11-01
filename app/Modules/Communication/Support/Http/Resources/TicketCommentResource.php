<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Http\Resources;

use App\Modules\Communication\Support\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reply on a ticket; `is_support` marks answers from the support desk, `is_me` the reader's own.
 *
 * @mixin TicketComment
 */
final class TicketCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => [
                'id' => $this->author_id,
                'full_name' => $this->author->person->full_name,
                'is_support' => $this->author->can('tickets.resolve'),
            ],
            'is_me' => $this->author_id === $request->user()?->getAuthIdentifier(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
