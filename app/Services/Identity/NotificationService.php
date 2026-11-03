<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\DeviceData;
use App\Enums\Identity\MobileApp;
use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\NotificationStatus;
use App\Enums\Identity\UserType;
use App\Exceptions\Identity\InvalidWebhookSignatureException;
use App\Exceptions\Identity\NotificationNotCancellableException;
use App\Exceptions\Identity\NotificationNotResendableException;
use App\Interfaces\Identity\NotificationChannelDriver;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Jobs\Identity\DeliverNotification;
use App\Models\Identity\DeviceToken;
use App\Models\Identity\Notification;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Throwable;

final class NotificationService implements NotificationServiceInterface
{
    private const DISPATCH_BATCH = 500;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
    ) {}

    public function send(string $template, Person $to, ?Model $related = null, array $data = [], ?CarbonImmutable $at = null, ?array $channels = null): Collection
    {
        $at ??= $this->clock->now();
        $channels ??= array_map(NotificationChannel::from(...), $this->defaultChannels($template));
        $ignoresOptOut = in_array($template, (array) Config::get('notifications.security_templates', []), true);
        $locale = in_array($to->language, ['en', 'ar'], true) ? $to->language : 'en';
        $user = $to->user;

        $rows = collect();
        foreach ($channels as $channel) {
            if (! $ignoresOptOut && in_array($channel->value, $to->notification_opt_out, true)) {
                continue;
            }
            $destination = $this->destination($channel, $to, $user);
            if ($destination === false) {
                continue;
            }

            $rows->push(Notification::query()->firstOrCreate([
                'person_id' => $to->id,
                'channel' => $channel,
                'template' => $template,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'scheduled_for' => $at,
            ], [
                'user_id' => $user?->id,
                'locale' => $locale,
                'destination' => $destination,
                'subject' => $this->render($template, 'subject', $data, $locale),
                'body' => (string) $this->render($template, 'body', $data, $locale),
            ]));
        }

        $rows->filter(fn (Notification $n): bool => $n->wasRecentlyCreated && $n->scheduled_for <= $this->clock->now())
            ->each(fn (Notification $n) => DeliverNotification::dispatch($n->id)->afterCommit());

        return $rows;
    }

    public function cancelFor(Model $related, ?string $template = null): int
    {
        return Notification::query()
            ->where('related_type', $related->getMorphClass())->where('related_id', $related->getKey())
            ->where('status', NotificationStatus::Queued)
            ->when($template !== null, fn ($q) => $q->where('template', $template))
            ->update(['status' => NotificationStatus::Cancelled]);
    }

    public function dispatchDue(): int
    {
        $due = Notification::query()->where('status', NotificationStatus::Queued)
            ->where('scheduled_for', '<=', $this->clock->now())
            ->orderBy('scheduled_for')->limit(self::DISPATCH_BATCH)->get();

        $due->each(fn (Notification $n) => $this->deliver($n));

        return $due->count();
    }

    public function deliver(Notification $notification): void
    {
        Cache::lock("notification-deliver:{$notification->id}", 120)->get(function () use ($notification): void {
            $notification->refresh();
            if ($notification->status !== NotificationStatus::Queued || $notification->scheduled_for > $this->clock->now()) {
                return;
            }

            $driver = $this->driver($notification->channel);
            $notification->attempts++;

            try {
                $notification->provider_message_id = $driver->send($notification);
                $notification->status = $driver->deliversImmediately() ? NotificationStatus::Delivered : NotificationStatus::Sent;
                $notification->sent_at = $this->clock->now();
                $notification->delivered_at = $driver->deliversImmediately() ? $notification->sent_at : null;
                $notification->last_error = null;
            } catch (Throwable $e) {
                report($e);
                $notification->last_error = Str::limit($e->getMessage(), 490);
                $backoff = (array) Config::get('notifications.backoff_minutes', [1, 5, 15]);
                if ($notification->attempts >= Notification::MAX_ATTEMPTS) {
                    $notification->status = NotificationStatus::Failed;
                } else {
                    $notification->scheduled_for = $this->clock->now()->addMinutes((int) ($backoff[$notification->attempts - 1] ?? 15));
                }
            }

            $notification->save();
        });
    }

    public function handleDeliveryWebhook(string $provider, array $payload, string $rawBody, ?string $signature): void
    {
        $secret = Config::get("notifications.webhooks.{$provider}.secret");
        if (! is_string($secret) || $secret === '' || $signature === null || ! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            throw new InvalidWebhookSignatureException;
        }

        $notification = Notification::query()->where('provider_message_id', (string) ($payload['message_id'] ?? ''))->firstOrFail();
        $delivered = ($payload['status'] ?? null) === NotificationStatus::Delivered->value;

        $notification->forceFill($delivered
            ? ['status' => NotificationStatus::Delivered, 'delivered_at' => $this->clock->now()]
            : ['status' => NotificationStatus::Failed, 'last_error' => Str::limit((string) ($payload['error'] ?? 'Provider reported a failure'), 490)])->save();
    }

    public function registerDevice(User $user, DeviceData $data): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(['token' => $data->token], [
            'user_id' => $user->id,
            'app' => $data->app,
            'platform' => $data->platform,
            'device_name' => $data->deviceName,
            'app_version' => $data->appVersion,
            'last_seen_at' => $this->clock->now(),
        ]);
    }

    public function unregisterDevice(User $user, string $token): void
    {
        DeviceToken::query()->where('user_id', $user->id)->where('token', $token)->firstOrFail()->delete();
    }

    public function forgetDevices(User $user): int
    {
        return (int) DeviceToken::query()->where('user_id', $user->id)->delete();
    }

    public function inbox(User $user, ListQuery $query, ?bool $unread = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = Notification::query()->where('user_id', $user->id)->where('channel', NotificationChannel::InApp)
            ->where('status', NotificationStatus::Delivered)
            // true = unread only, false = read only, null = both.
            ->when($unread === true, fn ($q) => $q->whereNull('read_at'))
            ->when($unread === false, fn ($q) => $q->whereNotNull('read_at'))
            ->orderByRaw('read_at IS NOT NULL');

        return $this->listQuery->paginate($builder, $query);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()->where('user_id', $user->id)->where('channel', NotificationChannel::InApp)
            ->where('status', NotificationStatus::Delivered)->whereNull('read_at')->count();
    }

    public function markRead(User $user, string $id): Notification
    {
        $notification = Notification::query()->where('user_id', $user->id)->where('channel', NotificationChannel::InApp)->findOrFail($id);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => $this->clock->now()])->save();
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()->where('user_id', $user->id)->where('channel', NotificationChannel::InApp)
            ->whereNull('read_at')->update(['read_at' => $this->clock->now()]);
    }

    public function log(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->visible(), $query);
    }

    public function find(string $id): Notification
    {
        return $this->visible()->findOrFail($id);
    }

    public function resend(Notification $notification): Notification
    {
        if (! in_array($notification->status, [NotificationStatus::Failed, NotificationStatus::Cancelled, NotificationStatus::Delivered], true)) {
            throw new NotificationNotResendableException;
        }

        $copy = $notification->replicate(['status', 'attempts', 'last_error', 'provider_message_id', 'sent_at', 'delivered_at', 'read_at', 'created_at']);
        $copy->scheduled_for = $this->clock->now();
        $copy->save();

        DeliverNotification::dispatch($copy->id)->afterCommit();

        return $copy->refresh();
    }

    public function cancel(Notification $notification): Notification
    {
        $updated = Notification::query()->whereKey($notification->id)->where('status', NotificationStatus::Queued)
            ->update(['status' => NotificationStatus::Cancelled]);
        if ($updated === 0) {
            throw new NotificationNotCancellableException;
        }

        return $notification->refresh();
    }

    /**
     * Snapshot of where the message goes; null for in-app, false when the channel cannot reach the person.
     */
    private function destination(NotificationChannel $channel, Person $to, ?User $user): string|false|null
    {
        return match ($channel) {
            NotificationChannel::Sms, NotificationChannel::Whatsapp => $to->phone !== '' ? $to->phone : false,
            NotificationChannel::Email => $to->email ?? false,
            NotificationChannel::InApp => $user === null ? false : null,
            NotificationChannel::Push => $user !== null && DeviceToken::query()->where('user_id', $user->id)->where('app', $this->appOf($user))->exists()
                ? $this->appOf($user)->value
                : false,
        };
    }

    /**
     * The template's channels from config. Template names contain dots (`appointment.booked`), so they are read
     * from the templates array, not with a dotted config key (which would split the name).
     *
     * @return list<string>
     */
    private function defaultChannels(string $template): array
    {
        $templates = (array) Config::get('notifications.templates', []);

        return array_values((array) ($templates[$template]['channels'] ?? [NotificationChannel::InApp->value]));
    }

    private function appOf(User $user): MobileApp
    {
        return $user->user_type === UserType::Patient ? MobileApp::Patient : MobileApp::Staff;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    private function render(string $template, string $part, array $data, string $locale): ?string
    {
        $key = "notifications.{$template}.{$part}";
        $text = __($key, array_map(fn ($v): string => (string) $v, $data), $locale);

        return is_string($text) && $text !== $key ? $text : null;
    }

    private function driver(NotificationChannel $channel): NotificationChannelDriver
    {
        $name = (string) Config::get("notifications.channels.{$channel->value}", 'log');
        /** @var class-string<NotificationChannelDriver> $class */
        $class = Config::get("notifications.drivers.{$name}");

        return app($class);
    }

    /**
     * @return Builder<Notification>
     */
    private function visible(): Builder
    {
        return OwnershipScope::apply(Notification::query()->with('person'), 'notifications', Auth::user());
    }
}
