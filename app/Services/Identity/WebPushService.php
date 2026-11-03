<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\PushSubscriptionData;
use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\UserType;
use App\Interfaces\Identity\WebPushServiceInterface;
use App\Jobs\Identity\SendWebPush;
use App\Models\Identity\Notification;
use App\Models\Identity\Person;
use App\Models\Identity\PushSubscription;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Config;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushService implements WebPushServiceInterface
{
    public function publicKey(): ?string
    {
        $key = Config::get('notifications.webpush.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function subscribe(User $user, PushSubscriptionData $data): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(['endpoint' => $data->endpoint], [
            'user_id' => $user->id,
            'public_key' => $data->publicKey,
            'auth_token' => $data->authToken,
            'user_agent' => $data->userAgent,
            'last_seen_at' => now(),
        ]);
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint', $endpoint)->delete();
    }

    public function enabled(Person $person): bool
    {
        return ! in_array(NotificationChannel::Push->value, $person->notification_opt_out, true);
    }

    public function setEnabled(Person $person, bool $enabled): bool
    {
        $others = array_values(array_diff($person->notification_opt_out, [NotificationChannel::Push->value]));
        $person->notification_opt_out = $enabled ? $others : [...$others, NotificationChannel::Push->value];
        $person->save();

        return $enabled;
    }

    public function queueFor(Notification $notification): void
    {
        $person = $notification->person;
        if ($this->publicKey() === null || $notification->user_id === null || ! $this->enabled($person)) {
            return;
        }
        if (PushSubscription::query()->where('user_id', $notification->user_id)->exists()) {
            SendWebPush::dispatch($notification->id)->afterCommit();
        }
    }

    public function send(Notification $notification): int
    {
        $subscriptions = PushSubscription::query()->where('user_id', $notification->user_id)->get();
        if ($subscriptions->isEmpty() || $this->publicKey() === null) {
            return 0;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => Config::string('notifications.webpush.subject'),
            'publicKey' => $this->publicKey(),
            'privateKey' => Config::string('notifications.webpush.private_key'),
        ]], ['TTL' => Config::integer('notifications.webpush.ttl_seconds', 86400)]);

        $payload = json_encode($this->payload($notification), JSON_THROW_ON_ERROR);
        foreach ($subscriptions as $subscription) {
            $push->queueNotification(new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, 'aes128gcm'), $payload);
        }

        $delivered = 0;
        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
            }
        }

        return $delivered;
    }

    /**
     * What the browser shows: title, text, and the inbox to open on a tap. `tag` keeps one entry per message.
     *
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function payload(Notification $notification): array
    {
        $patient = $notification->user?->user_type === UserType::Patient;
        $urls = (array) Config::get('notifications.webpush.inbox_urls', []);

        return [
            'title' => $notification->subject ?? Config::string('app.name'),
            'body' => $notification->body,
            'url' => (string) ($urls[$patient ? 'patient' : 'staff'] ?? '/'),
            'tag' => $notification->id,
        ];
    }
}
