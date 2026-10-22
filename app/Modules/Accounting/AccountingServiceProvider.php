<?php

declare(strict_types=1);

namespace App\Modules\Accounting;

use App\Modules\Accounting\Accounts\Contracts\AccountServiceInterface;
use App\Modules\Accounting\Accounts\Http\Resources\AccountResource;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Accounts\Services\AccountService;
use App\Modules\Accounting\Fiscal\Contracts\FiscalPeriodServiceInterface;
use App\Modules\Accounting\Fiscal\Services\FiscalPeriodService;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Http\Resources\JournalEntryResource;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Accounting\Journal\Services\JournalEntryService;
use App\Modules\Accounting\Reports\Contracts\FinancialReportServiceInterface;
use App\Modules\Accounting\Reports\Services\FinancialReportService;
use App\Shared\Bulk\BulkActionRegistry;
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
