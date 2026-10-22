<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Http\Requests;

use App\Enums\Accounting\AccountType;
use App\Enums\Transactions\ExpenseCategory;
use App\Modules\Transactions\Expenses\Data\ExpenseData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Expense form: a company payee or a free-text one; the account defaults from the category when left empty.
 */
final class ExpenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => [$this->isMethod('POST') ? 'prohibited' : 'required', 'integer'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'expense_account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('type', AccountType::Expense->value)->where('is_group', 0)->where('is_deleted', 0)],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where('is_deleted', 0)],
            'payee_company_id' => ['nullable', 'required_without:payee', 'uuid', Rule::exists('companies', 'id')->where('is_deleted', 0)],
            'payee' => ['nullable', 'required_without:payee_company_id', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
            'tax_amount' => ['nullable', 'decimal:0,3', 'min:0', 'lt:amount'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toData(): ExpenseData
    {
        $values = $this->validated();
        $values['tax_amount'] ??= '0';

        return ExpenseData::fromArray($values);
    }
}
