<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A chat was created, renamed, or its members changed: everyone concerned (including people just removed) reloads it.
 */
final class ConversationChanged implements ShouldBroadcastNow
{
    /**
     * @param  list<string>  $userIds
     */
    public function __construct(public readonly string $conversationId, public readonly array $userIds) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id): PrivateChannel => new PrivateChannel('user.'.$id), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'conversation.changed';
    }

    /**
     * @return array{conversation_id: string}
     */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId];
    }
}
