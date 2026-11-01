<?php

use App\Modules\Communication\Chat\Http\Controllers\ChatController;
use App\Modules\Communication\Support\Http\Controllers\SupportTicketController;
use App\Shared\Http\Middleware\EnsureStaff;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Communication API (prefix /api/v1) — staff chat and support tickets
|--------------------------------------------------------------------------
| Live updates arrive over the socket (Reverb): private channels user.{id}, conversation.{id} and support.
*/

Route::middleware(['auth:sanctum', EnsureStaff::ALIAS])->group(function (): void {
    // Staff chat: one-to-one and groups. Membership and group-admin rules are enforced in the service.
    Route::prefix('chat')->name('chat.')->controller(ChatController::class)->group(function (): void {
        Route::middleware(['access:chat.read_own|chat.read_all', 'throttle:read'])->group(function (): void {
            Route::get('conversations', 'index')->name('index');
            Route::get('conversations/{conversation}', 'show')->whereUuid('conversation')->name('show');
            Route::get('conversations/{conversation}/messages', 'messages')->whereUuid('conversation')->name('messages');
            Route::get('unread', 'unread')->name('unread');
            Route::get('contacts', 'contacts')->name('contacts');
            Route::get('settings', 'settings')->name('settings');
        });
        Route::middleware(['access:chat.create', 'throttle:write'])->group(function (): void {
            Route::post('conversations', 'store')->name('store');
            Route::post('conversations/{conversation}/messages', 'send')->whereUuid('conversation')->name('send');
        });
        Route::middleware(['access:chat.write', 'throttle:write'])->group(function (): void {
            Route::post('conversations/{conversation}/read', 'read')->whereUuid('conversation')->name('read');
            Route::post('conversations/{conversation}/messages/{message}/reactions', 'react')->whereUuid(['conversation', 'message'])->name('react');
            Route::put('settings', 'saveSettings')->name('settings.save');
            Route::patch('conversations/{conversation}', 'rename')->whereUuid('conversation')->name('rename');
            Route::post('conversations/{conversation}/members', 'addMembers')->whereUuid('conversation')->name('members.add');
            Route::patch('conversations/{conversation}/members/{user}', 'setRole')->whereUuid(['conversation', 'user'])->name('members.role');
            Route::delete('conversations/{conversation}/members/{user}', 'removeMember')->whereUuid(['conversation', 'user'])->name('members.remove');
        });
    });

    // Support tickets: staff open and follow their own; admins (read_all + resolve) work them.
    Route::prefix('support/tickets')->name('support.tickets.')->controller(SupportTicketController::class)->group(function (): void {
        Route::middleware(['access:tickets.read_own|tickets.read_all', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{ticket}', 'show')->whereUuid('ticket')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:tickets.create')->name('store');
            Route::post('{ticket}/comments', 'comment')->whereUuid('ticket')->middleware('access:tickets.write')->name('comments.store');
            Route::patch('{ticket}/status', 'status')->whereUuid('ticket')->middleware('access:tickets.write&tickets.resolve')->name('status');
        });
    });
});
