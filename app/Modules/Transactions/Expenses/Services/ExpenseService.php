<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Modules\Transactions\Expenses\Contracts\ExpenseServiceInterface;
use App\Modules\Transactions\Expenses\Data\ExpenseData;
use App\Modules\Transactions\Expenses\Data\PayExpenseData;
use App\Modules\Transactions\Expenses\Events\ExpenseStatusChanged;
use App\Modules\Transactions\Expenses\Exceptions\ExpenseApprovalLimitException;
use App\Modules\Transactions\Expenses\Exceptions\ExpenseNotEditableException;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SelfApprovalException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use App\Shared\Support\RoleLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Operating expenses through the shared approval workflow, then payment (posted to the journal).
 *
 * @extends BaseEntityService<Expense>
 */
final class ExpenseService extends BaseEntityService implements ExpenseServiceInterface
{
    public const RELATIONS = ['payeeCompany', 'department', 'expenseAccount'];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly CashSessionServiceInterface $cashSessions,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function find(string $id): Expense
    {
        return $this->query()->with(['submitter.person', 'approver.person', 'attachments'])->findOrFail($id);
    }

    public function findWithDeleted(string $id): Expense
    {
        return parent::findWithDeleted($id);
    }

    public function restore(Model $model): Expense
    {
        return $model->isDeleted() ? $this->find(parent::restore($model)->id) : $model;
    }

    public function create(ExpenseData $data): Expense
    {
        return DB::transaction(function () use ($data): Expense {
            $expense = new Expense($data->values);
            $expense->expense_account_id = $data->values['expense_account_id'] ?? $this->defaultAccount((string) $data->values['category']);
            $expense->status = ApprovalStatus::Draft;
            $expense->save();

            return $this->find($expense->id);
        });
    }

    public function update(Expense $expense, ExpenseData $data, int $version): Expense
    {
        return DB::transaction(function () use ($expense, $data, $version): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $expense->assertVersion($version);
            if ($expense->status !== ApprovalStatus::Draft) {
                throw new ExpenseNotEditableException;
            }
            $expense->fill($data->values);
            if (! array_key_exists('expense_account_id', $data->values) || $data->values['expense_account_id'] === null) {
                $expense->expense_account_id = $this->defaultAccount($expense->category->value);
            }
            $expense->save();

            return $this->find($expense->id);
        });
    }

    public function submit(Expense $expense): Expense
    {
        return $this->transition($expense, ApprovalStatus::Draft, ApprovalStatus::Pending, ['submitted_by' => Auth::id()]);
    }

    public function approve(Expense $expense): Expense
    {
        $limit = RoleLimit::best(Auth::user(), Config::array('payments.expense_approval_limits'));
        if ($limit !== null && $expense->amount->getAmount()->isGreaterThan($limit)) {
            throw new ExpenseApprovalLimitException(__('errors.EXPENSE_APPROVAL_LIMIT', ['limit' => MoneyHelper::format(MoneyHelper::of((string) $limit))]), ['limit' => (string) $limit]);
        }

        return $this->transition($expense, ApprovalStatus::Pending, ApprovalStatus::Approved, ['approved_by' => Auth::id(), 'approved_at' => $this->clock->now()], segregated: true);
    }

    public function reject(Expense $expense, string $reason): Expense
    {
        return $this->transition($expense, ApprovalStatus::Pending, ApprovalStatus::Rejected, ['rejection_reason' => $reason], segregated: true);
    }

    public function pay(Expense $expense, PayExpenseData $data): Expense
    {
        $expense = DB::transaction(function () use ($expense, $data): Expense {
            $expense = Expense::query()->with('payeeCompany')->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== ApprovalStatus::Approved || $expense->isPaid()) {
                throw new InvalidStateTransitionException(context: ['from' => $expense->status->value, 'to' => 'paid']);
            }
            $actor = Auth::user();
            $session = $data->method === PaymentMethod::Cash && $actor !== null ? $this->cashSessions->requireOpen($actor) : null;

            /** @var array<string, AccountMappingKey> $accounts */
            $accounts = Config::array('accounting.payment_method_accounts');
            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $expense,
                $data->paidAt,
                __('accounting.expense_paid', ['code' => $expense->code, 'payee' => $expense->payeeName()]),
                [
                    JournalLineData::debit($expense->expense_account_id, $expense->net(), $expense->description, departmentId: $expense->department_id),
                    JournalLineData::debit(AccountMappingKey::InputVat, $expense->tax_amount),
                    JournalLineData::credit($accounts[$data->method->value], $expense->amount),
                ],
            ));
            $expense->forceFill([
                'paid_at' => $this->clock->combine($data->paidAt, $this->clock->toClinic($this->clock->now())->format('H:i')),
                'payment_method' => $data->method,
                'payment_reference' => $data->reference,
                'cash_session_id' => $session?->id,
                'journal_entry_id' => $entry->id,
            ])->save();

            return $expense;
        });
        ExpenseStatusChanged::dispatch($expense);

        return $this->find($expense->id);
    }

    public function cancel(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $open = in_array($expense->status, [ApprovalStatus::Draft, ApprovalStatus::Pending, ApprovalStatus::Approved], true);
            if (! $open || $expense->isPaid()) {
                throw new InvalidStateTransitionException(context: ['from' => $expense->status->value, 'to' => ApprovalStatus::Cancelled->value]);
            }
            $expense->forceFill(['status' => ApprovalStatus::Cancelled])->save();

            return $this->find($expense->id);
        });
    }

    /** Only unpaid drafts and cancelled / rejected expenses can be deleted. */
    protected function beforeDelete(Model $model): void
    {
        /** @var Expense $model */
        if ($model->isPaid() || in_array($model->status, [ApprovalStatus::Pending, ApprovalStatus::Approved], true)) {
            throw new InvalidStateTransitionException(context: ['from' => $model->status->value, 'to' => 'deleted']);
        }
    }

    /**
     * @return Builder<Expense>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Expense::query()->with(self::RELATIONS), 'expenses', Auth::user());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function transition(Expense $expense, ApprovalStatus $from, ApprovalStatus $to, array $values, bool $segregated = false): Expense
    {
        $expense = DB::transaction(function () use ($expense, $from, $to, $values, $segregated): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== $from) {
                throw new InvalidStateTransitionException(context: ['from' => $expense->status->value, 'to' => $to->value]);
            }
            if ($segregated && $expense->submitted_by === Auth::id()) {
                throw new SelfApprovalException;
            }
            $expense->forceFill(['status' => $to, ...$values])->save();

            return $expense;
        });
        ExpenseStatusChanged::dispatch($expense);

        return $this->find($expense->id);
    }

    private function defaultAccount(string $category): string
    {
        $code = Config::array('accounting.expense_category_accounts')[$category] ?? null;

        return Account::query()->where('code', $code)->valueOrFail('id');
    }
}
