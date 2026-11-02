<?php

use App\Http\Controllers\Transactions\CashSessionController;
use App\Http\Controllers\Transactions\ExpenseController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Transactions API (prefix /api/v1/finance) — cash drawer shifts and expenses
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('finance')->name('finance.')->group(function (): void {

    Route::prefix('cash-sessions')->name('cash-sessions.')->controller(CashSessionController::class)->group(function (): void {
        Route::get('current', 'current')->middleware(['access:(cash_sessions.read_all|cash_sessions.read_own)&cash_sessions.operate', 'throttle:read'])->name('current');
        Route::middleware(['access:cash_sessions.read_all|cash_sessions.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{cashSession}', 'show')->whereUuid('cashSession')->name('show');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('/', 'open')->middleware('access:cash_sessions.create&cash_sessions.operate')->name('open');
            Route::post('{cashSession}/close', 'close')->whereUuid('cashSession')->middleware('access:cash_sessions.write&cash_sessions.operate')->name('close');
            Route::post('{cashSession}/approve-difference', 'approveDifference')->whereUuid('cashSession')->middleware('access:cash_sessions.write&cash_sessions.supervise')->name('approve-difference');
            Route::post('{cashSession}/deposit', 'deposit')->whereUuid('cashSession')->middleware(['access:cash_sessions.write&cash_sessions.supervise', EnsureIdempotency::ALIAS])->name('deposit');
        });
    });
    Route::dataTransfer('cash-sessions', 'cash_sessions');

    Route::prefix('expenses')->name('expenses.')->controller(ExpenseController::class)->group(function (): void {
        Route::middleware(['access:expenses.read_all|expenses.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{expense}', 'show')->whereUuid('expense')->name('show');
        });
        Route::middleware(['access:expenses.create', 'throttle:write'])->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{expense}', 'update')->whereUuid('expense')->name('update');
            Route::post('{expense}/submit', 'submit')->whereUuid('expense')->name('submit');
            Route::post('{expense}/cancel', 'cancel')->whereUuid('expense')->name('cancel');
            Route::delete('{expense}', 'destroy')->whereUuid('expense')->name('destroy');
            Route::patch('{expense}/restore', 'restore')->whereUuid('expense')->name('restore');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('{expense}/approve', 'approve')->whereUuid('expense')->middleware('access:expenses.write&expenses.approve')->name('approve');
            Route::post('{expense}/reject', 'reject')->whereUuid('expense')->middleware('access:expenses.write&expenses.approve')->name('reject');
            Route::post('{expense}/pay', 'pay')->whereUuid('expense')->middleware(['access:expenses.write&expenses.pay', EnsureIdempotency::ALIAS])->name('pay');
        });
    });
    Route::bulkActions('expenses', 'expenses')->middleware('access:expenses.create|expenses.approve');
    Route::dataTransfer('expenses', 'expenses');
});
