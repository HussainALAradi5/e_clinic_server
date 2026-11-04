<?php

declare(strict_types=1);

namespace App\Modules\Accounting;

use App\Http\Resources\Accounting\AccountResource;
use App\Http\Resources\Accounting\JournalEntryResource;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Interfaces\Accounting\FinancialReportServiceInterface;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Accounting\MonthSummaryServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\MonthSummaryService;
use App\Services\Bulk\BulkActionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

/**
 * Accounting module: chart of accounts and mappings, fiscal years and periods, the general journal
 * (the single financial ledger) and the financial reports read from it.
 */
final class AccountingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MonthSummaryServiceInterface::class => MonthSummaryService::class,
        AccountServiceInterface::class => AccountService::class,
        FiscalPeriodServiceInterface::class => FiscalPeriodService::class,
        JournalEntryServiceInterface::class => JournalEntryService::class,
        FinancialReportServiceInterface::class => FinancialReportService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        $bulk->register('accounts', Account::class, Account::listQueryConfig(), AccountService::class)
            ->exportable(AccountResource::class, fn (Builder $q) => $q->with('parent'), values: [
                'parent' => fn (Model $a): string => $a instanceof Account && $a->parent !== null ? $a->parent->code.' '.$a->parent->name : '',
            ]);

        // Posted entries never change: only drafts can be posted or deleted in bulk.
        $bulk->register('journal', JournalEntry::class, JournalEntry::listQueryConfig(), genericActions: false)
            ->action('post', 'journal.write&journal.post', [JournalEntryServiceInterface::class, 'post'])
            ->action('delete', 'journal.create', [JournalEntryServiceInterface::class, 'deleteDraft'], inverse: 'restore')
            ->action('restore', 'journal.create', [JournalEntryServiceInterface::class, 'restoreDraft'], targetsDeleted: true, inverse: 'delete')
            ->exportable(JournalEntryResource::class);
    }
}
