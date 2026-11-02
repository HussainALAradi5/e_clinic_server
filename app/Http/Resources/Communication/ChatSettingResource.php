<?php

declare(strict_types=1);

namespace App\Http\Resources\Communication;

use App\Models\Communication\ChatSetting;
use App\Models\Communication\MessageReaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * My chat settings, plus the emojis the reaction picker offers.
 *
 * @mixin ChatSetting
 */
final class ChatSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bubble_style' => $this->bubble_style->value,
            'my_color' => $this->my_color->value,
            'others_color' => $this->others_color->value,
            'notify_messages' => $this->notify_messages,
            'count_in_badge' => $this->count_in_badge,
            'reactions' => MessageReaction::EMOJIS,
            'all_reactions' => MessageReaction::ALL_EMOJIS,
        ];
    }
}
