<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Events;

use App\Modules\Communication\Chat\Http\Resources\MessageResource;
use App\Modules\Communication\Chat\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A new message: to the open chat (`conversation.{id}`) and to every member's own channel (`user.{id}`), which
 * moves the chat to the top of their list and bumps its unread badge.
 */
final class MessageSent implements ShouldBroadcastNow
{
    /**
     * @param  list<string>  $memberIds
     */
    public function __construct(public readonly Message $message, public readonly array $memberIds) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
            ...array_map(fn (string $id): PrivateChannel => new PrivateChannel('user.'.$id), $this->memberIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new MessageResource($this->message->loadMissing('sender.person')))->resolve();
    }
}
