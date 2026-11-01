<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Contracts;

use App\Enums\Communication\ParticipantRole;
use App\Modules\Communication\Chat\Data\ConversationFilter;
use App\Modules\Communication\Chat\Data\SendMessageData;
use App\Modules\Communication\Chat\Models\ChatSetting;
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
     * My chats, newest activity first, each with my unread count and its message count; filtered by name, type
     * (group / one-to-one), read state and last-activity dates.
     *
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function conversations(User $me, ConversationFilter $filter): LengthAwarePaginator;

    /**
     * A chat I am in (`CHAT_NOT_A_MEMBER` otherwise; `CHAT_DELETED` once deleted). With `$orDeleted`, an admin
     * (chat.read_all) also gets a deleted chat, to read it.
     */
    public function find(User $me, string $id, bool $orDeleted = false): Conversation;

    /** Soft-deletes the chat (never removed): either person in a one-to-one chat, a leader in a group. */
    public function delete(User $me, Conversation $conversation): void;

    /** An admin brings a deleted chat back to its members. */
    public function restore(User $me, string $id): Conversation;

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

    /** Text, files or a voice note (attachments of the message); @mentioned members get a notice. */
    public function send(User $me, Conversation $conversation, SendMessageData $data): Message;

    /** Sets my emoji on a message; the same emoji again (or null) removes it. */
    public function react(User $me, Conversation $conversation, string $messageId, ?string $emoji): Message;

    /** My chat settings (defaults until saved). */
    public function settings(User $me): ChatSetting;

    /**
     * @param  array<string, mixed>  $values  validated bubble_style, my_color, others_color, notify_messages, count_in_badge
     */
    public function saveSettings(User $me, array $values): ChatSetting;

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
