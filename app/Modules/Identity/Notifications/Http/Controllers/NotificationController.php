<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Http\Controllers;

use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Notifications\Http\Requests\RegisterDeviceRequest;
use App\Modules\Identity\Notifications\Http\Resources\NotificationResource;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotificationController
{
    use ApiResponse;

    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    /** Own in-app inbox (meta.unread_count drives the bell badge). */
    public function inbox(Request $request): JsonResponse
    {
        $request->validate(['read' => ['nullable', 'in:unread,read']]);
        $user = $this->user($request);
        $query = ListQuery::fromRequest($request, Notification::listQueryConfig());
        // `read=unread|read` (the inbox page chips); `unread=1` is the older form the bell uses.
        $unread = $request->filled('read') ? $request->input('read') === 'unread' : ($request->boolean('unread') ? true : null);
        $page = $this->notifications->inbox($user, $query, $unread);

        return $this->paginated(NotificationResource::collection($page), $query, extraMeta: ['unread_count' => $this->notifications->unreadCount($user)]);
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->notifications->registerDevice($this->user($request), $request->toData());

        return $this->created(['id' => $device->id, 'app' => $device->app->value, 'platform' => $device->platform->value]);
    }

    public function unregisterDevice(Request $request, string $token): Response
    {
        $this->notifications->unregisterDevice($this->user($request), $token);

        return $this->noContent();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return $this->ok(['updated' => $this->notifications->markAllRead($this->user($request))]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        return $this->ok(new NotificationResource($this->notifications->markRead($this->user($request), $notification)));
    }

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Notification::listQueryConfig());

        return $this->paginated(NotificationResource::collection($this->notifications->log($query)), $query);
    }

    public function show(string $notification): JsonResponse
    {
        return $this->ok(new NotificationResource($this->notifications->find($notification)));
    }

    public function resend(string $notification): JsonResponse
    {
        return $this->created(new NotificationResource($this->notifications->resend($this->notifications->find($notification))));
    }

    public function cancel(string $notification): JsonResponse
    {
        return $this->ok(new NotificationResource($this->notifications->cancel($this->notifications->find($notification))));
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $this->notifications->handleDeliveryWebhook($provider, $payload, $request->getContent(), $request->header('X-Signature'));

        return $this->ok();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
