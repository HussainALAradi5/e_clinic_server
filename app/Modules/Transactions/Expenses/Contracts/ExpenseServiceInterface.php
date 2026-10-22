<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Contracts;

use App\Modules\Transactions\Expenses\Data\ExpenseData;
use App\Modules\Transactions\Expenses\Data\PayExpenseData;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExpenseServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Expense>|CursorPaginator<int, Expense>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Expense with its vendor-invoice scans. */
    public function find(string $id): Expense;

    /** Expense including soft-deleted ones (restore). */
    public function findWithDeleted(string $id): Expense;

    /** Soft-deletes an unpaid draft, cancelled or rejected expense. */
    public function delete(Expense $expense): void;

    public function restore(Expense $expense): Expense;

    /** Draft; the expense account defaults from the category. */
    public function create(ExpenseData $data): Expense;

    /** Edits a draft (optimistic lock). */
    public function update(Expense $expense, ExpenseData $data, int $version): Expense;

    /** `draft → pending`; approvers are notified. */
    public function submit(Expense $expense): Expense;

    /** `pending → approved` by someone other than the submitter, within the approver's limit. */
    public function approve(Expense $expense): Expense;

    /** `pending → rejected`. */
    public function reject(Expense $expense, string $reason): Expense;

    /** Pays an approved expense: Dr expense (+ input VAT) / Cr cash or bank. */
    public function pay(Expense $expense, PayExpenseData $data): Expense;

    /** Cancels an unpaid draft / pending / approved expense. */
    public function cancel(Expense $expense): Expense;
}
