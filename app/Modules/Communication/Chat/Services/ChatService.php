<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Services;

use App\Enums\Communication\ConversationType;
use App\Enums\Communication\MessageKind;
use App\Enums\Communication\ParticipantRole;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Modules\Communication\Chat\Contracts\ChatServiceInterface;
use App\Modules\Communication\Chat\Data\ConversationFilter;
use App\Modules\Communication\Chat\Data\SendMessageData;
use App\Modules\Communication\Chat\Events\ConversationChanged;
use App\Modules\Communication\Chat\Events\ConversationRead;
use App\Modules\Communication\Chat\Events\MessageReacted;
use App\Modules\Communication\Chat\Events\MessageSent;
use App\Modules\Communication\Chat\Exceptions\GroupAdminRequiredException;
use App\Modules\Communication\Chat\Exceptions\InvalidChatPartnerException;
use App\Modules\Communication\Chat\Exceptions\NotAParticipantException;
use App\Modules\Communication\Chat\Models\ChatSetting;
use App\Modules\Communication\Chat\Models\Conversation;
use App\Modules\Communication\Chat\Models\ConversationParticipant;
use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Communication\Chat\Models\MessageReaction;
use App\Modules\Identity\Attachments\Contracts\AttachmentServiceInterface;
use App\Modules\Identity\Attachments\Data\AttachToData;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\Recipients;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChatService implements ChatServiceInterface
{
    /** Loaded with every chat: members (with names) and the last message. */
    private const RELATIONS = ['participants.user.person', 'latestMessage.sender.person'];

    /** Loaded with every message: who sent it, its files and its reactions. */
    private const MESSAGE_RELATIONS = ['sender.person', 'attachments', 'reactions.user.person'];

    public function __construct(
        private readonly ClinicClock $clock,
        private readonly AttachmentServiceInterface $attachments,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function conversations(User $me, ConversationFilter $filter): LengthAwarePaginator
    {
        $words = preg_split('/\s+/', trim((string) $filter->search), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $activity = 'COALESCE(conversations.last_message_at, conversations.created_at)';

        return $this->mine($me)
            ->withCount('messages')
            // Every word must match the group name or another member's first / last name ("sara nasser").
            ->when($words !== [], function (Builder $q) use ($words, $me): void {
                foreach ($words as $word) {
                    $like = '%'.$word.'%';
                    $q->where(fn (Builder $w) => $w->whereLike('title', $like)->orWhereHas(
                        'participants.user.person',
                        fn (Builder $p) => $p->where('users.id', '!=', $me->id)->where(fn (Builder $n) => $n->whereLike('first_name', $like)->orWhereLike('last_name', $like)),
                    ));
                }
            })
            ->when($filter->type !== null, fn (Builder $q) => $q->where('conversations.type', $filter->type))
            ->when($filter->unread === true, fn (Builder $q) => $q->whereExists($this->unreadMessages($me)->toBase()))
            ->when($filter->unread === false, fn (Builder $q) => $q->whereNotExists($this->unreadMessages($me)->toBase()))
            ->when($filter->from !== null, fn (Builder $q) => $q->whereRaw("{$activity} >= ?", [$filter->from?->startOfDay()]))
            ->when($filter->to !== null, fn (Builder $q) => $q->whereRaw("{$activity} <= ?", [$filter->to?->endOfDay()]))
            ->orderByRaw("{$activity} DESC")
            ->paginate($filter->perPage);
    }

    public function find(User $me, string $id): Conversation
    {
        $conversation = $this->mine($me)->find($id);

        return $conversation instanceof Conversation ? $conversation : throw new NotAParticipantException;
    }

    public function startDirect(User $me, string $partnerId): Conversation
    {
        $partner = $this->colleague($me, $partnerId);
        $key = Conversation::directKey($me->id, $partner->id);

        $id = DB::transaction(function () use ($me, $partner, $key): string {
            $existing = Conversation::query()->where('direct_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing->id;
            }
            $conversation = Conversation::query()->create(['type' => ConversationType::Direct, 'direct_key' => $key, 'created_by' => $me->id]);
            foreach ([$me->id, $partner->id] as $userId) {
                $conversation->participants()->create(['user_id' => $userId, 'role' => ParticipantRole::Member]);
            }

            return $conversation->id;
        });

        return $this->find($me, $id);
    }

    public function createGroup(User $me, string $title, array $memberIds): Conversation
    {
        $members = array_values(array_unique(array_filter($memberIds, fn (string $id): bool => $id !== $me->id)));
        foreach ($members as $id) {
            $this->colleague($me, $id);
        }

        $conversation = DB::transaction(function () use ($me, $title, $members): Conversation {
            $conversation = Conversation::query()->create(['type' => ConversationType::Group, 'title' => $title, 'created_by' => $me->id]);
            $conversation->participants()->create(['user_id' => $me->id, 'role' => ParticipantRole::Admin]);
            foreach ($members as $id) {
                $conversation->participants()->create(['user_id' => $id, 'role' => ParticipantRole::Member]);
            }

            return $conversation;
        });
        $this->announce($conversation->id, [$me->id, ...$members]);
        $this->notify('chat.added', $members, $conversation, $me);

        return $this->find($me, $conversation->id);
    }

    public function rename(User $me, Conversation $conversation, string $title): Conversation
    {
        $this->assertAdmin($me, $conversation);
        $conversation->update(['title' => $title]);
        $this->announce($conversation->id, $this->memberIds($conversation));

        return $this->find($me, $conversation->id);
    }

    public function addMembers(User $me, Conversation $conversation, array $userIds): Conversation
    {
        $this->assertAdmin($me, $conversation);
        $added = [];
        DB::transaction(function () use ($me, $conversation, $userIds, &$added): void {
            foreach (array_unique($userIds) as $id) {
                $this->colleague($me, $id);
                $row = ConversationParticipant::query()->firstOrNew(['conversation_id' => $conversation->id, 'user_id' => $id]);
                if ($row->exists && $row->left_at === null) {
                    continue;
                }
                // Someone who left and comes back starts unread from now, not from the old history.
                $row->fill(['role' => ParticipantRole::Member, 'left_at' => null, 'last_read_at' => $this->clock->now()])->save();
                $added[] = $id;
            }
        });
        $this->announce($conversation->id, [...$this->memberIds($conversation->refresh()), ...$added]);
        $this->notify('chat.added', $added, $conversation, $me);

        return $this->find($me, $conversation->id);
    }

    public function removeMember(User $me, Conversation $conversation, string $userId): void
    {
        if (! $conversation->isGroup()) {
            throw new GroupAdminRequiredException;
        }
        if ($userId !== $me->id) {
            $this->assertAdmin($me, $conversation);
        }
        $leaving = $conversation->participantFor($userId) ?? throw new NotAParticipantException;
        $before = $this->memberIds($conversation);

        DB::transaction(function () use ($conversation, $leaving): void {
            $leaving->update(['left_at' => $this->clock->now(), 'role' => ParticipantRole::Member]);
            $rest = $conversation->participants()->orderBy('created_at')->get();
            // A group is never left without an admin: the longest-standing member takes over.
            if ($rest->isNotEmpty() && ! $rest->contains(fn (ConversationParticipant $p): bool => $p->isAdmin())) {
                $rest->first()->update(['role' => ParticipantRole::Admin]);
            }
        });
        $this->announce($conversation->id, $before);
        if ($userId !== $me->id) {
            $this->notify('chat.removed', [$userId], $conversation, $me);
        }
    }

    public function setRole(User $me, Conversation $conversation, string $userId, ParticipantRole $role): Conversation
    {
        $this->assertAdmin($me, $conversation);
        $member = $conversation->participantFor($userId) ?? throw new NotAParticipantException;
        $admins = $conversation->participants->filter(fn (ConversationParticipant $p): bool => $p->isAdmin());
        if ($role === ParticipantRole::Member && $member->isAdmin() && $admins->count() === 1) {
            // The last admin hands over first (promote someone), so the group always has one.
            throw new GroupAdminRequiredException(__('errors.CHAT_LAST_ADMIN'));
        }
        $member->update(['role' => $role]);
        $this->announce($conversation->id, $this->memberIds($conversation));
        $this->notify('chat.role', [$userId], $conversation, $me, ['role' => $role->label()]);

        return $this->find($me, $conversation->id);
    }

    public function messages(User $me, Conversation $conversation, ?string $before, int $limit): Collection
    {
        $query = $conversation->messages()->with(self::MESSAGE_RELATIONS)->orderByDesc('created_at')->orderByDesc('id');
        if ($before !== null) {
            $anchor = $conversation->messages()->find($before);
            if ($anchor instanceof Message) {
                $query->where(fn (Builder $q) => $q->where('created_at', '<', $anchor->created_at)
                    ->orWhere(fn (Builder $same) => $same->where('created_at', $anchor->created_at)->where('id', '<', $anchor->id)));
            }
        }

        return $this->withSenderRoles($conversation, $query->limit($limit)->get())->toBase();
    }

    public function send(User $me, Conversation $conversation, SendMessageData $data): Message
    {
        $members = $this->memberIds($conversation);
        // Only current members can be mentioned, and never yourself.
        $mentions = array_values(array_diff(array_intersect($data->mentions, $members), [$me->id]));
        $kind = match (true) {
            $data->voice => MessageKind::Voice,
            $data->files !== [] => MessageKind::Media,
            default => MessageKind::Text,
        };
        $category = $data->voice ? AttachmentCategory::VoiceNote : AttachmentCategory::ChatMedia;

        $now = $this->clock->now();
        $message = DB::transaction(function () use ($me, $conversation, $data, $mentions, $kind, $category, $now): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $me->id,
                'body' => $data->body,
                'kind' => $kind,
                'mentions' => $mentions === [] ? null : $mentions,
            ]);
            // Files go through the attachments module (type and size rules, scan), owned by the message.
            foreach ($data->files as $file) {
                $this->attachments->uploadOne($file, new AttachToData('chat_message', $message->id, $category));
            }
            $conversation->update(['last_message_at' => $now]);
            // Writing in a chat means having read it.
            $conversation->participantFor($me->id)?->update(['last_read_at' => $now]);

            return $message;
        });
        $this->withSenderRoles($conversation, $message->newCollection([$message->load(self::MESSAGE_RELATIONS)]));
        rescue(fn () => broadcast(new MessageSent($message, $members)), report: true);
        $this->notify('chat.mention', $mentions, $conversation, $me, ['excerpt' => mb_strimwidth((string) $data->body, 0, 80, '…')]);

        return $message;
    }

    public function react(User $me, Conversation $conversation, string $messageId, ?string $emoji): Message
    {
        $message = $conversation->messages()->findOrFail($messageId);
        $current = MessageReaction::query()->where(['message_id' => $message->id, 'user_id' => $me->id])->first();

        // The same emoji again (or none) takes the reaction back; another one replaces it.
        if ($emoji === null || $current?->emoji === $emoji) {
            $current?->delete();
        } else {
            MessageReaction::query()->updateOrCreate(['message_id' => $message->id, 'user_id' => $me->id], ['emoji' => $emoji]);
        }

        $this->withSenderRoles($conversation, $message->newCollection([$message->load(self::MESSAGE_RELATIONS)]));
        rescue(fn () => broadcast(new MessageReacted($message)), report: true);

        return $message;
    }

    public function settings(User $me): ChatSetting
    {
        return ChatSetting::query()->firstOrNew(['user_id' => $me->id]);
    }

    public function saveSettings(User $me, array $values): ChatSetting
    {
        $settings = $this->settings($me);
        $settings->fill($values)->save();

        return $settings;
    }

    public function markRead(User $me, Conversation $conversation): void
    {
        $now = $this->clock->now();
        $conversation->participantFor($me->id)?->update(['last_read_at' => $now]);
        rescue(fn () => broadcast(new ConversationRead($conversation->id, $me->id, $now)), report: true);
    }

    public function unreadTotal(User $me): int
    {
        return (int) Message::query()
            ->join('conversation_participants as p', fn ($j) => $j->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', $me->id)->whereNull('p.left_at'))
            ->where('messages.sender_id', '!=', $me->id)
            ->where(fn (Builder $q) => $q->whereNull('p.last_read_at')->orWhereColumn('messages.created_at', '>', 'p.last_read_at'))
            ->count();
    }

    public function contacts(User $me, ?string $search, int $limit): Collection
    {
        return User::query()
            ->with('person')
            ->where('users.id', '!=', $me->id)
            ->where('status', UserStatus::Active)
            ->where('user_type', '!=', UserType::Patient)
            ->join('persons', 'persons.id', '=', 'users.id')
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('persons.first_name', '%'.$search.'%')
                ->orWhereLike('persons.last_name', '%'.$search.'%')))
            ->orderBy('persons.first_name')
            ->orderBy('persons.last_name')
            ->limit($limit)
            ->get(['users.*'])
            ->toBase();
    }

    /**
     * Chats I am a current member of, with my unread count.
     *
     * @return Builder<Conversation>
     */
    private function mine(User $me): Builder
    {
        return Conversation::query()
            ->with(self::RELATIONS)
            ->join('conversation_participants as mine', fn ($j) => $j->on('mine.conversation_id', '=', 'conversations.id')->where('mine.user_id', $me->id)->whereNull('mine.left_at'))
            ->select('conversations.*')
            ->selectSub($this->unreadMessages($me)->selectRaw('COUNT(*)'), 'unread_count');
    }

    /**
     * Messages from others since I last read the chat (a subquery on `mine`, the joined membership row).
     *
     * @return Builder<Message>
     */
    private function unreadMessages(User $me): Builder
    {
        return Message::query()
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.sender_id', '!=', $me->id)
            ->where(fn (Builder $q) => $q->whereNull('mine.last_read_at')->orWhereColumn('messages.created_at', '>', 'mine.last_read_at'));
    }

    /**
     * Marks each message with its sender's role in a group (the leader's bubbles look different).
     *
     * @param  EloquentCollection<int, Message>  $messages
     * @return EloquentCollection<int, Message>
     */
    private function withSenderRoles(Conversation $conversation, EloquentCollection $messages): EloquentCollection
    {
        $roles = $conversation->participants->mapWithKeys(fn (ConversationParticipant $p): array => [$p->user_id => $p->role->value]);
        $messages->each(fn (Message $m) => $m->setAttribute('sender_role', $conversation->isGroup() ? $roles->get($m->sender_id) : null));

        return $messages;
    }

    /**
     * In-app notice for group events (added, removed, new role) and @mentions — never for plain messages.
     *
     * @param  list<string>  $userIds
     * @param  array<string, string>  $data
     */
    private function notify(string $template, array $userIds, Conversation $conversation, User $by, array $data = []): void
    {
        if ($userIds === []) {
            return;
        }
        $data = [
            'chat' => $conversation->isGroup() ? (string) $conversation->title : (string) __('enums.ConversationType.direct'),
            'by' => $by->person->full_name,
            ...$data,
        ];
        foreach ($userIds as $id) {
            Recipients::user($id)->each(fn (Person $p) => rescue(fn () => $this->notifications->send($template, $p, $conversation, $data), report: true));
        }
    }

    /** An active staff member other than me. */
    private function colleague(User $me, string $id): User
    {
        $user = User::query()->find($id);
        if (! $user instanceof User || $user->id === $me->id || ! $user->isActive() || $user->user_type === UserType::Patient) {
            throw new InvalidChatPartnerException;
        }

        return $user;
    }

    private function assertAdmin(User $me, Conversation $conversation): void
    {
        if (! $conversation->isGroup() || ! ($conversation->participantFor($me->id)?->isAdmin() ?? false)) {
            throw new GroupAdminRequiredException;
        }
    }

    /**
     * @return list<string>
     */
    private function memberIds(Conversation $conversation): array
    {
        return array_values(array_map(strval(...), $conversation->participants()->pluck('user_id')->all()));
    }

    /**
     * @param  list<string>  $userIds
     */
    private function announce(string $conversationId, array $userIds): void
    {
        rescue(fn () => broadcast(new ConversationChanged($conversationId, array_values(array_unique($userIds)))), report: true);
    }
}
