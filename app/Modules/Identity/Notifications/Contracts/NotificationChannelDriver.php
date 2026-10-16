<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Contracts;

use App\Modules\Identity\Notifications\Models\Notification;

/**
 * Sends one notification row through a provider. Throws on failure (the row is retried with back-off).
 */
interface NotificationChannelDriver
{
    /** Returns the provider message id (used to match delivery webhooks), if any. */
    public function send(Notification $notification): ?string;

    /** True when the provider confirms delivery synchronously (in-app). */
    public function deliversImmediately(): bool;
}
