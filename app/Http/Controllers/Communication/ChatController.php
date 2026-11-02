<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communication;

use App\Dtos\Communication\ConversationFilter;
use App\Enums\Communication\BubbleColor;
use App\Enums\Communication\BubbleStyle;
use App\Enums\Communication\ConversationType;
use App\Enums\Communication\ParticipantRole;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Communication\SendMessageRequest;
use App\Http\Requests\Communication\StartConversationRequest;
use App\Http\Resources\Communication\ChatSettingResource;
use App\Http\Resources\Communication\ContactResource;
use App\Http\Resources\Communication\ConversationResource;
use App\Http\Resources\Communication\MessageResource;
use App\Interfaces\Communication\ChatServiceInterface;
use App\Models\Communication\MessageReaction;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Staff chat: my chats, one-to-one and groups, messages, read receipts and the colleague directory. */
final class ChatController
{
    use ApiResponse;

    private const MESSAGES_PER_PAGE = 50;

    public function __construct(private readonly ChatServiceInterface $chat) {}

    /**
     * My chats: `search`, `type` (group|direct), `read` (unread|read), `from` / `to` (last activity), `per_page`;
     * `deleted=1` (admins) lists every deleted chat instead.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(ConversationType::class)],
            'read' => ['nullable', Rule::in(['unread', 'read'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'deleted' => ['nullable', 'boolean'],
        ]);
        $page = $this->chat->conversations($this->me($request), new ConversationFilter(
            search: $request->string('search')->toString() ?: null,
            type: $request->enum('type', ConversationType::class),
            unread: $request->filled('read') ? $request->input('read') === 'unread' : null,
            from: $request->filled('from') ? CarbonImmutable::parse($request->string('from')->toString()) : null,
            to: $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString()) : null,
            perPage: $request->integer('per_page', 30),
            deleted: $request->boolean('deleted'),
        ));

        return $this->paginated(ConversationResource::collection($page));
    }

    public function store(StartConversationRequest $request): JsonResponse
    {
        $me = $this->me($request);
        $conversation = $request->input('type') === 'direct'
            ? $this->chat->startDirect($me, $request->string('user_id')->toString())
            : $this->chat->createGroup($me, $request->string('title')->toString(), array_values(array_map('strval', (array) $request->input('member_ids', []))));

        return $this->created(new ConversationResource($conversation));
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        return $this->ok(new ConversationResource($this->chat->find($this->me($request), $conversation, orDeleted: true)));
    }

    /** Soft delete: the chat leaves its members' lists; admins can still read and restore it. */
    public function destroy(Request $request, string $conversation): Response
    {
        $me = $this->me($request);
        $this->chat->delete($me, $this->chat->find($me, $conversation));

        return $this->noContent();
    }

    public function restore(Request $request, string $conversation): JsonResponse
    {
        return $this->ok(new ConversationResource($this->chat->restore($this->me($request), $conversation)));
    }

    public function rename(Request $request, string $conversation): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'min:2', 'max:100']]);
        $me = $this->me($request);

        return $this->ok(new ConversationResource($this->chat->rename($me, $this->chat->find($me, $conversation), $request->string('title')->toString())));
    }

    public function addMembers(Request $request, string $conversation): JsonResponse
    {
        $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:100'], 'user_ids.*' => ['uuid', 'distinct']]);
        $me = $this->me($request);

        return $this->ok(new ConversationResource($this->chat->addMembers($me, $this->chat->find($me, $conversation), array_values(array_map('strval', (array) $request->input('user_ids'))))));
    }

    /** An admin removes a member, or a member leaves (their own id). */
    public function removeMember(Request $request, string $conversation, string $user): Response
    {
        $me = $this->me($request);
        $this->chat->removeMember($me, $this->chat->find($me, $conversation), $user);

        return $this->noContent();
    }

    public function setRole(Request $request, string $conversation, string $user): JsonResponse
    {
        $request->validate(['role' => ['required', Rule::enum(ParticipantRole::class)]]);
        $me = $this->me($request);

        return $this->ok(new ConversationResource($this->chat->setRole($me, $this->chat->find($me, $conversation), $user, ParticipantRole::from($request->string('role')->toString()))));
    }

    /** Newest first, 50 at a time; `before` (a message id) loads older ones. */
    public function messages(Request $request, string $conversation): JsonResponse
    {
        $request->validate(['before' => ['nullable', 'uuid']]);
        $me = $this->me($request);
        $messages = $this->chat->messages($me, $this->chat->find($me, $conversation, orDeleted: true), $request->string('before')->toString() ?: null, self::MESSAGES_PER_PAGE);

        return $this->ok(MessageResource::collection($messages), meta: ['has_more' => $messages->count() === self::MESSAGES_PER_PAGE]);
    }

    public function send(SendMessageRequest $request, string $conversation): JsonResponse
    {
        $me = $this->me($request);

        return $this->created(new MessageResource($this->chat->send($me, $this->chat->find($me, $conversation), $request->toData())));
    }

    /** `emoji` one of the offered reactions; the same one again (or null) takes it back. */
    public function react(Request $request, string $conversation, string $message): JsonResponse
    {
        $request->validate(['emoji' => ['nullable', 'string', Rule::in(MessageReaction::ALL_EMOJIS)]]);
        $me = $this->me($request);
        $emoji = $request->string('emoji')->toString();

        return $this->ok(new MessageResource($this->chat->react($me, $this->chat->find($me, $conversation), $message, $emoji === '' ? null : $emoji)));
    }

    public function settings(Request $request): JsonResponse
    {
        return $this->ok(new ChatSettingResource($this->chat->settings($this->me($request))));
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $values = $request->validate([
            'bubble_style' => ['sometimes', Rule::enum(BubbleStyle::class)],
            'my_color' => ['sometimes', Rule::enum(BubbleColor::class)],
            'others_color' => ['sometimes', Rule::enum(BubbleColor::class)],
            'notify_messages' => ['sometimes', 'boolean'],
            'count_in_badge' => ['sometimes', 'boolean'],
        ]);

        return $this->ok(new ChatSettingResource($this->chat->saveSettings($this->me($request), $values)));
    }

    public function read(Request $request, string $conversation): Response
    {
        $me = $this->me($request);
        $this->chat->markRead($me, $this->chat->find($me, $conversation));

        return $this->noContent();
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->ok(['count' => $this->chat->unreadTotal($this->me($request))]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return $this->ok(ContactResource::collection($this->chat->contacts($this->me($request), $request->string('search')->toString() ?: null, 50)));
    }

    private function me(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
