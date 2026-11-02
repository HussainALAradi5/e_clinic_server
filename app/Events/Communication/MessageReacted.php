<?php

declare(strict_types=1);

namespace App\Events\Communication;

use App\Http\Resources\Communication\MessageResource;
use App\Models\Communication\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Someone reacted to (or took back a reaction on) a message: the open chat redraws that message. */
final class MessageReacted implements ShouldBroadcastNow
{
    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.'.$this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.reacted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new MessageResource($this->message))->resolve();
    }
}
