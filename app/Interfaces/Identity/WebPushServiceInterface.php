<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Dtos\Identity\PushSubscriptionData;
use App\Models\Identity\Notification;
use App\Models\Identity\Person;
use App\Models\Identity\PushSubscription;
use App\Models\Identity\User;

/**
 * Phone and browser notifications: the person's on / off switch (the `push` opt-out) and Web Push to the browsers
 * they subscribed. Every in-app message is also pushed while the switch is on.
 */
interface WebPushServiceInterface
{
    /** The server's VAPID public key (the browser needs it to subscribe); null when Web Push is not set up. */
    public function publicKey(): ?string;

    public function subscribe(User $user, PushSubscriptionData $data): PushSubscription;

    public function unsubscribe(User $user, string $endpoint): void;

    /** True unless the person turned phone and browser notifications off. */
    public function enabled(Person $person): bool;

    /** Turns phone and browser notifications on or off (the `push` opt-out); returns the new state. */
    public function setEnabled(Person $person, bool $enabled): bool;

    /** Queues a push of this in-app message to the person's browsers (when on and subscribed). */
    public function queueFor(Notification $notification): void;

    /** Sends the message to every browser of its user; browsers that are gone are forgotten. Returns how many got it. */
    public function send(Notification $notification): int;
}
