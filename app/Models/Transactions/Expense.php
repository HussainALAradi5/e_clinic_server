<?php

declare(strict_types=1);

namespace App\Models\Transactions;

use App\Casts\MoneyCast;
use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Enums\Transactions\ExpenseCategory;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Accounting\Account;
use App\Models\Base\BaseModel;
use App\Models\HR\Department;
use App\Models\Identity\Attachment;
use App\Models\Identity\User;
use App\Models\Reference\Company;
use App\Traits\Shared\HasListQuery;
use App\Traits\Shared\HasVersion;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Operating expense: draft → pending → approved (by someone else) → paid; `amount` includes recoverable VAT.
 *
 * @property string $id
 * @property string $code
 * @property ExpenseCategory $category
 * @property string $expense_account_id
 * @property ?string $department_id
 * @property ?string $payee_company_id
 * @property ?string $payee
 * @property string $description
 * @property Money $amount
 * @property Money $tax_amount
 * @property CarbonImmutable $expense_date
 * @property ?string $vendor_invoice_number
 * @property ApprovalStatus $status
 * @property ?string $submitted_by
 * @property ?string $approved_by
 * @property ?CarbonImmutable $approved_at
 * @property ?string $rejection_reason
 * @property ?CarbonImmutable $paid_at
 * @property ?PaymentMethod $payment_method
 * @property ?string $payment_reference
 * @property ?string $cash_session_id
 * @property ?string $journal_entry_id
 * @property ?string $created_by
 * @property int $version
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Account $expenseAccount
 * @property-read ?Department $department
 * @property-read ?Company $payeeCompany
 * @property-read ?User $submitter
 * @property-read ?User $approver
 * @property-read Collection<int, Attachment> $attachments
 */
class Expense extends BaseModel implements OwnedByUser
{
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'EXP';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('expenses')
            ->sortable('expense_date', 'code', 'amount', 'status', 'category', 'paid_at', 'created_at')
            ->filterable('status', ['eq', 'in', 'nin'], enum: ApprovalStatus::class)
            ->filterable('category', ['eq', 'in'], enum: ExpenseCategory::class)
            ->filterable('department_id', ['eq', 'in'], resource: 'departments')
            ->filterable('payee_company_id', ['eq'], resource: 'companies', label: 'Supplier / company')
            ->filterable('expense_account_id', ['eq'], resource: 'accounts')
            ->filterable('paid_at', ['null'], type: 'datetime')
            ->filterable('amount', ['gte', 'lte', 'between'], type: 'money')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('vendor_invoice_number')
            ->includes('payeeCompany', 'department', 'expenseAccount')
            ->column('code', 'Code')
            ->column('expense_date', 'Date')
            ->column('payee', 'Payee')
            ->column('description', 'Description')
            ->column('category', 'Category')
            ->column('amount', 'Amount')
            ->column('status', 'Status')
            ->column('paid_at', 'Paid')
            ->dateFields('expense_date', 'paid_at', 'created_at')
            ->defaultSort('-expense_date');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function payeeName(): string
    {
        return $this->payeeCompany !== null ? $this->payeeCompany->name : (string) $this->payee;
    }

    /** Amount before recoverable VAT. */
    public function net(): Money
    {
        return $this->amount->minus($this->tax_amount);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id')->withDeleted();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withDeleted();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function payeeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payee_company_id')->withDeleted();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->created_by, $this->submitted_by], true);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        $id = $user->getAuthIdentifier();

        return $query->where(fn (Builder $q) => $q->where('expenses.created_by', $id)->orWhere('expenses.submitted_by', $id));
    }

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'status' => ApprovalStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'expense_date' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
