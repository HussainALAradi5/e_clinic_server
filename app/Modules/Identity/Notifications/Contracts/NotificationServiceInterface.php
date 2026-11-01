<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Contracts;

use App\Enums\Identity\NotificationChannel;
use App\Modules\Identity\Notifications\Data\DeviceData;
use App\Modules\Identity\Notifications\Models\DeviceToken;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface NotificationServiceInterface
{
    /**
     * **Internal API used by all modules** — renders the template in the person's language, applies opt-outs and
     * queues one row per channel (dispatched after commit). A duplicate (same dedupe key) returns the existing row.
     *
     * @param  array<string, scalar|null>  $data  template placeholders
     * @param  list<NotificationChannel>|null  $channels  null = the template's default channels
     * @return Collection<int, Notification>
     */
    public function send(string $template, Person $to, ?Model $related = null, array $data = [], ?CarbonImmutable $at = null, ?array $channels = null): Collection;

    /** **Internal** — cancels queued notifications of a record (e.g. rescheduled appointment). */
    public function cancelFor(Model $related, ?string $template = null): int;

    /** **Scheduled (every minute)** — sends due queued rows; returns how many were handed to drivers. */
    public function dispatchDue(): int;

    /** **Internal** — sends one queued row through its channel driver (retries with back-off, max 3 attempts). */
    public function deliver(Notification $notification): void;

    /**
     * Provider delivery receipt (signature = HMAC-SHA256 of the raw body).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleDeliveryWebhook(string $provider, array $payload, string $rawBody, ?string $signature): void;

    public function registerDevice(User $user, DeviceData $data): DeviceToken;

    public function unregisterDevice(User $user, string $token): void;

    /** Deletes every push token of the user (sign out of all devices). */
    public function forgetDevices(User $user): int;

    /**
     * Current user's in-app notifications, unread first.
     *
     * @return LengthAwarePaginator<int, Notification>|CursorPaginator<int, Notification>
     */
    public function inbox(User $user, ListQuery $query, ?bool $unread = null): LengthAwarePaginator|CursorPaginator;

    public function unreadCount(User $user): int;

    public function markRead(User $user, string $id): Notification;

    public function markAllRead(User $user): int;

    /**
     * Delivery log (read_all | own).
     *
     * @return LengthAwarePaginator<int, Notification>|CursorPaginator<int, Notification>
     */
    public function log(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): Notification;

    /** Re-queues a failed / cancelled / delivered message as a new row. */
    public function resend(Notification $notification): Notification;

    /** queued → cancelled. */
    public function cancel(Notification $notification): Notification;
}
