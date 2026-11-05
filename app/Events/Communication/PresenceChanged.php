<?php

declare(strict_types=1);

namespace App\Events\Communication;

use App\Enums\Identity\PresenceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A staff member went Online or Offline (sign-in, sign-out on the last device, or the switch): chat dots update live. */
final class PresenceChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId, public readonly PresenceStatus $presence, public readonly CarbonImmutable $at) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff.presence')];
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    /**
     * @return array{user_id: string, presence: string, at: string}
     */
    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'presence' => $this->presence->value, 'at' => $this->at->toIso8601String()];
    }
}
