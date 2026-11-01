<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Contracts;

use App\Enums\Communication\ParticipantRole;
use App\Modules\Communication\Chat\Models\Conversation;
use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Staff chat. Every call is made as `$me`: only members read or write a chat, only group admins change a group.
 */
interface ChatServiceInterface
{
    /**
     * My chats, newest activity first, each with my unread count.
     *
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function conversations(User $me, ?string $search, int $perPage): LengthAwarePaginator;

    /** A chat I am in (`CHAT_NOT_A_MEMBER` otherwise). */
    public function find(User $me, string $id): Conversation;

    /** The one direct chat with a colleague, created on first use. */
    public function startDirect(User $me, string $partnerId): Conversation;

    /**
     * A new group with me as its admin.
     *
     * @param  list<string>  $memberIds
     */
    public function createGroup(User $me, string $title, array $memberIds): Conversation;

    public function rename(User $me, Conversation $conversation, string $title): Conversation;

    /**
     * @param  list<string>  $userIds
     */
    public function addMembers(User $me, Conversation $conversation, array $userIds): Conversation;

    /** An admin removes someone, or anyone leaves (removing themself); the group always keeps an admin. */
    public function removeMember(User $me, Conversation $conversation, string $userId): void;

    public function setRole(User $me, Conversation $conversation, string $userId, ParticipantRole $role): Conversation;

    /**
     * Messages newest first, `$limit` at a time; `$before` (a message id) loads the older ones.
     *
     * @return Collection<int, Message>
     */
    public function messages(User $me, Conversation $conversation, ?string $before, int $limit): Collection;

    public function send(User $me, Conversation $conversation, string $body): Message;

    /** Marks the chat read up to now. */
    public function markRead(User $me, Conversation $conversation): void;

    /** Unread messages over all my chats (the badge on the Chat menu). */
    public function unreadTotal(User $me): int;

    /**
     * Active colleagues I can message (never patients, never me).
     *
     * @return Collection<int, User>
     */
    public function contacts(User $me, ?string $search, int $limit): Collection;
}
