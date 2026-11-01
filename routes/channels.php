<?php

use App\Enums\Identity\UserType;
use App\Modules\Communication\Chat\Models\ConversationParticipant;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Private broadcast channels (authorised at POST /api/v1/broadcasting/auth with the Sanctum token)
|--------------------------------------------------------------------------
*/

// Everything addressed to one user: new messages in any of their chats, chat changes, their tickets.
Broadcast::channel('user.{id}', fn (User $user, string $id): bool => $user->id === $id);

// One open chat: its messages and read receipts, for current members only.
Broadcast::channel('conversation.{id}', fn (User $user, string $id): bool => ConversationParticipant::query()
    ->where('conversation_id', $id)->where('user_id', $user->id)->whereNull('left_at')->exists());

// The support desk: every ticket change, for the admins who resolve tickets.
Broadcast::channel('support', fn (User $user): bool => $user->user_type !== UserType::Patient && $user->can('tickets.resolve'));
