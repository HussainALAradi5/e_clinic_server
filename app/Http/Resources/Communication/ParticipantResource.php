<?php

declare(strict_types=1);

namespace App\Http\Resources\Communication;

use App\Models\Communication\ConversationParticipant;
use App\Utilities\Identity\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member with the badges the chat shows: their role in the group, "you", and what they do at the clinic.
 *
 * @mixin ConversationParticipant
 */
final class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'full_name' => $this->user->person->full_name,
            'avatar_url' => Avatar::url($this->user->person),
            // Work contact of a colleague (chat is staff-only), for the chat's info panel.
            'phone' => $this->user->person->phone,
            'email' => $this->user->person->email,
            'user_type' => $this->user->user_type->value,
            'presence' => $this->user->presence->value,
            'role' => $this->role->value,
            'is_me' => $this->user_id === $request->user()?->getAuthIdentifier(),
            'last_read_at' => $this->last_read_at?->toIso8601String(),
        ];
    }
}
