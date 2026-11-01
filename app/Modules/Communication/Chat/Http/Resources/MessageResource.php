<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Resources;

use App\Modules\Communication\Chat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'sender' => ['id' => $this->sender_id, 'full_name' => $this->sender->person->full_name],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
