<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\FinancialReportController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\MonthSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting API (prefix /accounting) — chart of accounts, fiscal years, journal, reports
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('accounting')->name('accounting.')->group(function (): void {

    Route::prefix('accounts')->name('accounts.')->controller(AccountController::class)->group(function (): void {
        Route::middleware(['access:accounts.read_all|accounts.read_own', 'throttle:read'])->group(function (): void {
            Route::get('tree', 'tree')->name('tree');
            Route::get('mappings', 'mappings')->name('mappings');
            Route::get('/', 'index')->name('index');
            Route::get('{account}', 'show')->whereUuid('account')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::put('mappings', 'updateMappings')->middleware('access:accounts.write')->name('mappings.update');
            Route::post('/', 'store')->middleware('access:accounts.create')->name('store');
            Route::put('{account}', 'update')->whereUuid('account')->middleware('access:accounts.write')->name('update');
            Route::patch('{account}/archive', 'archive')->whereUuid('account')->middleware('access:accounts.write')->name('archive');
            Route::patch('{account}/unarchive', 'unarchive')->whereUuid('account')->middleware('access:accounts.write')->name('unarchive');
            Route::delete('{account}', 'destroy')->whereUuid('account')->middleware('access:accounts.delete')->name('destroy');
        });
        Route::patch('{account}/restore', 'restore')->whereUuid('account')->middleware(['access:accounts.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('accounts', 'accounts')->middleware('access:accounts.write|accounts.delete');
    Route::dataTransfer('accounts', 'accounts');

    Route::prefix('fiscal-years')->name('fiscal.')->controller(FiscalPeriodController::class)->group(function (): void {
        Route::get('/', 'index')->middleware(['access:fiscal.read_all|fiscal.read_own', 'throttle:read'])->name('index');
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('/', 'store')->middleware('access:fiscal.create')->name('store');
            Route::post('periods/{period}/close', 'closePeriod')->whereUuid('period')->middleware('access:fiscal.write&fiscal.close')->name('periods.close');
            Route::post('periods/{period}/reopen', 'reopenPeriod')->whereUuid('period')->middleware(['access:fiscal.write&fiscal.reopen', 'ensure.2fa'])->name('periods.reopen');
            Route::post('{fiscalYear}/close', 'closeYear')->whereUuid('fiscalYear')->middleware('access:fiscal.write&fiscal.close')->name('close');
            Route::post('{fiscalYear}/lock', 'lockYear')->whereUuid('fiscalYear')->middleware(['access:fiscal.write&fiscal.reopen', 'ensure.2fa'])->name('lock');
        });
    });

    Route::prefix('journal-entries')->name('journal.')->controller(JournalEntryController::class)->group(function (): void {
        Route::middleware(['access:journal.read_all|journal.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{journalEntry}', 'show')->whereUuid('journalEntry')->name('show');
        });
        Route::middleware(['access:journal.create', 'throttle:write'])->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{journalEntry}', 'update')->whereUuid('journalEntry')->name('update');
            Route::delete('{journalEntry}', 'destroy')->whereUuid('journalEntry')->name('destroy');
            Route::patch('{journalEntry}/restore', 'restore')->whereUuid('journalEntry')->name('restore');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('{journalEntry}/post', 'post')->whereUuid('journalEntry')->middleware('access:journal.write&journal.post')->name('post');
            Route::post('{journalEntry}/reverse', 'reverse')->whereUuid('journalEntry')->middleware('access:journal.write&journal.reverse')->name('reverse');
        });
    });
    Route::bulkActions('journal-entries', 'journal')->middleware('access:journal.write|journal.delete|journal.post');
    Route::dataTransfer('journal-entries', 'journal');

    // Every report shares one controller action; the route default names the report (ReportType value).
    // The owner's month-end summary (with the month before).
    Route::get('month-summary', [MonthSummaryController::class, 'show'])->middleware(['access:reports.read_all', 'throttle:report'])->name('month-summary');

    Route::prefix('reports')->name('reports.')->controller(FinancialReportController::class)->middleware('access:reports.read_all|reports.read_own')->group(function (): void {
        foreach (['trial_balance', 'general_ledger', 'income_statement', 'balance_sheet', 'cash_flow', 'revenue_by_doctor', 'revenue_by_service', 'receivables_aging'] as $report) {
            Route::get(str_replace('_', '-', $report), 'show')->defaults('report', $report)->middleware('throttle:read')->name(str_replace('_', '-', $report));
        }
        Route::post('export', 'export')->middleware(['access:reports.export', 'throttle:report'])->name('export');
    });
});
