<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A member read the chat up to now: the senders' ticks turn blue, the reader's other devices clear the badge. */
final class ConversationRead implements ShouldBroadcastNow
{
    public function __construct(public readonly string $conversationId, public readonly string $userId, public readonly CarbonImmutable $readAt) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId), new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array{conversation_id: string, user_id: string, read_at: string}
     */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'user_id' => $this->userId, 'read_at' => $this->readAt->toIso8601String()];
    }
}
