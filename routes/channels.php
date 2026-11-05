<?php

use App\Enums\Identity\UserType;
use App\Models\Communication\ConversationParticipant;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Private broadcast channels (authorised at POST /broadcasting/auth with the Sanctum token)
|--------------------------------------------------------------------------
*/

// Everything addressed to one user: new messages in any of their chats, chat changes, their tickets.
Broadcast::channel('user.{id}', fn (User $user, string $id): bool => $user->id === $id);

// One open chat: its messages and read receipts, for current members only.
Broadcast::channel('conversation.{id}', fn (User $user, string $id): bool => ConversationParticipant::query()
    ->where('conversation_id', $id)->where('user_id', $user->id)->whereNull('left_at')->exists());

// Online / Offline of staff members, for every staff member who uses chat (the dots in chat lists).
Broadcast::channel('staff.presence', fn (User $user): bool => $user->user_type !== UserType::Patient && $user->can('chat.read_own'));

// The support desk: every ticket change, for the admins who resolve tickets.
Broadcast::channel('support', fn (User $user): bool => $user->user_type !== UserType::Patient && $user->can('tickets.resolve'));
