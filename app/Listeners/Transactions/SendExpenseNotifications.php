<?php

declare(strict_types=1);

namespace App\Listeners\Transactions;

use App\Enums\Shared\ApprovalStatus;
use App\Events\Transactions\ExpenseStatusChanged;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Models\Identity\Person;
use App\Utilities\Shared\MoneyHelper;
use App\Utilities\Shared\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Expense workflow in the app: a submitted expense reaches the approvers; approval or rejection reaches the submitter.
 * Paying it out tells nobody (the submitter sees it on the list).
 */
final class SendExpenseNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(ExpenseStatusChanged $event): void
    {
        $expense = $event->expense->loadMissing('payeeCompany');
        if ($expense->isPaid()) {
            return;
        }
        $data = ['code' => $expense->code, 'amount' => MoneyHelper::format($expense->amount), 'payee' => $expense->payeeName(), 'reason' => (string) $expense->rejection_reason];

        [$template, $people] = match ($expense->status) {
            ApprovalStatus::Pending => ['expense.submitted', Recipients::withPermission('expenses.approve', [$expense->submitted_by])],
            ApprovalStatus::Approved => ['expense.approved', Recipients::user($expense->submitted_by)],
            ApprovalStatus::Rejected => ['expense.rejected', Recipients::user($expense->submitted_by)],
            default => [null, collect()],
        };
        if ($template === null) {
            return;
        }

        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $expense, $data));
    }
}
