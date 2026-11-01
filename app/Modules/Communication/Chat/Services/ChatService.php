<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Services;

use App\Enums\Communication\ConversationType;
use App\Enums\Communication\ParticipantRole;
use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Modules\Communication\Chat\Contracts\ChatServiceInterface;
use App\Modules\Communication\Chat\Events\ConversationChanged;
use App\Modules\Communication\Chat\Events\ConversationRead;
use App\Modules\Communication\Chat\Events\MessageSent;
use App\Modules\Communication\Chat\Exceptions\GroupAdminRequiredException;
use App\Modules\Communication\Chat\Exceptions\InvalidChatPartnerException;
use App\Modules\Communication\Chat\Exceptions\NotAParticipantException;
use App\Modules\Communication\Chat\Models\Conversation;
use App\Modules\Communication\Chat\Models\ConversationParticipant;
use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\ClinicClock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChatService implements ChatServiceInterface
{
    /** Loaded with every chat: members (with names) and the last message. */
    private const RELATIONS = ['participants.user.person', 'latestMessage.sender.person'];

    public function __construct(private readonly ClinicClock $clock) {}

    public function conversations(User $me, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->mine($me)
            ->when($search !== null && $search !== '', function (Builder $q) use ($search, $me): void {
                $like = '%'.$search.'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhereHas(
                    'participants.user.person',
                    fn (Builder $p) => $p->where('users.id', '!=', $me->id)->where(fn (Builder $n) => $n->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)),
                ));
            })
            ->orderByRaw('COALESCE(conversations.last_message_at, conversations.created_at) DESC')
            ->paginate($perPage);
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

        return $this->find($me, $conversation->id);
    }

    public function messages(User $me, Conversation $conversation, ?string $before, int $limit): Collection
    {
        $query = $conversation->messages()->with('sender.person')->orderByDesc('created_at')->orderByDesc('id');
        if ($before !== null) {
            $anchor = $conversation->messages()->find($before);
            if ($anchor instanceof Message) {
                $query->where(fn (Builder $q) => $q->where('created_at', '<', $anchor->created_at)
                    ->orWhere(fn (Builder $same) => $same->where('created_at', $anchor->created_at)->where('id', '<', $anchor->id)));
            }
        }

        return $query->limit($limit)->get()->toBase();
    }

    public function send(User $me, Conversation $conversation, string $body): Message
    {
        $now = $this->clock->now();
        $message = DB::transaction(function () use ($me, $conversation, $body, $now): Message {
            $message = $conversation->messages()->create(['sender_id' => $me->id, 'body' => $body]);
            $conversation->update(['last_message_at' => $now]);
            // Writing in a chat means having read it.
            $conversation->participantFor($me->id)?->update(['last_read_at' => $now]);

            return $message;
        });
        rescue(fn () => broadcast(new MessageSent($message, $this->memberIds($conversation))), report: true);

        return $message->load('sender.person');
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
                ->where('persons.first_name', 'like', '%'.$search.'%')
                ->orWhere('persons.last_name', 'like', '%'.$search.'%')))
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
        $unread = Message::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.sender_id', '!=', $me->id)
            ->where(fn (Builder $q) => $q->whereNull('mine.last_read_at')->orWhereColumn('messages.created_at', '>', 'mine.last_read_at'));

        return Conversation::query()
            ->with(self::RELATIONS)
            ->join('conversation_participants as mine', fn ($j) => $j->on('mine.conversation_id', '=', 'conversations.id')->where('mine.user_id', $me->id)->whereNull('mine.left_at'))
            ->select('conversations.*')
            ->selectSub($unread, 'unread_count');
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
