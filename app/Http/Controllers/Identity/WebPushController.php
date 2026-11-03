<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\SubscribeWebPushRequest;
use App\Interfaces\Identity\WebPushServiceInterface;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Phone and browser notifications of the signed-in person: the on / off switch and this browser's subscription.
 */
final class WebPushController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WebPushServiceInterface $push) {}

    /** `{ push: bool, web_push_key: ?string }` — the switch, and the key a browser needs to subscribe. */
    public function settings(Request $request): JsonResponse
    {
        return $this->ok($this->state($this->user($request)));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate(['push' => ['required', 'boolean']]);
        $user = $this->user($request);
        $this->push->setEnabled($user->person, (bool) $validated['push']);

        return $this->ok($this->state($user));
    }

    public function subscribe(SubscribeWebPushRequest $request): JsonResponse
    {
        $subscription = $this->push->subscribe($this->user($request), $request->toData());

        return $this->created(['id' => $subscription->id]);
    }

    public function unsubscribe(Request $request): Response
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        $this->push->unsubscribe($this->user($request), (string) $validated['endpoint']);

        return $this->noContent();
    }

    /**
     * @return array{push: bool, web_push_key: ?string}
     */
    private function state(User $user): array
    {
        return ['push' => $this->push->enabled($user->person), 'web_push_key' => $this->push->publicKey()];
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
