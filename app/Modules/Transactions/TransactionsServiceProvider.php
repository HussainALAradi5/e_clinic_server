<?php

declare(strict_types=1);

namespace App\Modules\Transactions;

use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Modules\Transactions\CashSessions\Http\Resources\CashSessionResource;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Modules\Transactions\CashSessions\Services\CashSessionService;
use App\Modules\Transactions\Expenses\Contracts\ExpenseServiceInterface;
use App\Modules\Transactions\Expenses\Events\ExpenseStatusChanged;
use App\Modules\Transactions\Expenses\Http\Resources\ExpenseResource;
use App\Modules\Transactions\Expenses\Listeners\SendExpenseNotifications;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Modules\Transactions\Expenses\Services\ExpenseService;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Support\MoneyHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Transactions module: cash drawer shifts (every cash document belongs to the handler's open drawer) and expenses.
 * Finance exports share the `transactions.export` right.
 */
final class TransactionsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        CashSessionServiceInterface::class => CashSessionService::class,
        ExpenseServiceInterface::class => ExpenseService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen(ExpenseStatusChanged::class, SendExpenseNotifications::class);

        $bulk->register('cash_sessions', CashSession::class, CashSession::listQueryConfig(), genericActions: false)
            ->exportable(CashSessionResource::class, fn (Builder $q) => $q->with('cashier.person'), values: [
                'cashier' => fn (Model $s): string => $s instanceof CashSession ? $s->cashier->person->full_name : '',
                'difference' => fn (Model $s): string => $s instanceof CashSession && $s->difference !== null ? MoneyHelper::format($s->difference) : '',
            ])
            ->permissionsOf('transactions');

        $bulk->register('expenses', Expense::class, Expense::listQueryConfig(), genericActions: false)
            ->action('submit', 'expenses.create', [ExpenseServiceInterface::class, 'submit'])
            ->action('approve', 'expenses.write&expenses.approve', [ExpenseServiceInterface::class, 'approve'])
            ->exportable(ExpenseResource::class, fn (Builder $q) => $q->with(ExpenseService::RELATIONS), values: [
                'amount' => fn (Model $e): string => $e instanceof Expense ? MoneyHelper::format($e->amount) : '',
                'payee' => fn (Model $e): string => $e instanceof Expense ? $e->payeeName() : '',
            ])
            ->permissionsOf('transactions');
    }
}
