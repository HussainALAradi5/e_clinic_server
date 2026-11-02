<?php

declare(strict_types=1);

namespace App\Http\Resources\Transactions;

use App\Http\Resources\Shared\Summary;
use App\Models\Transactions\Expense;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expense claim: draft → pending → approved / rejected → paid (`paid_at`).
 *
 * @mixin Expense
 */
final class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'category' => $this->category->value,
            'expense_account_id' => $this->expense_account_id,
            'expense_account' => $this->whenLoaded('expenseAccount', fn (): array => ['id' => $this->expenseAccount->id, 'code' => $this->expenseAccount->code, 'name' => $this->expenseAccount->name]),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (): ?array => $this->department === null ? null : ['id' => $this->department->id, 'name' => $this->department->name]),
            'payee_company_id' => $this->payee_company_id,
            'payee_company' => $this->whenLoaded('payeeCompany', fn (): ?array => $this->payeeCompany === null ? null : ['id' => $this->payeeCompany->id, 'name' => $this->payeeCompany->name]),
            'payee' => $this->payee,
            'payee_name' => $this->relationLoaded('payeeCompany') ? $this->payeeName() : $this->payee,
            'description' => $this->description,
            'amount' => MoneyHelper::toApi($this->amount),
            'tax_amount' => MoneyHelper::toApi($this->tax_amount),
            'expense_date' => $this->expense_date->toDateString(),
            'vendor_invoice_number' => $this->vendor_invoice_number,
            'status' => $this->status->value,
            'is_paid' => $this->isPaid(),
            'submitted_by' => $this->whenLoaded('submitter', fn (): ?array => Summary::user($this->submitter)),
            'approved_by' => $this->whenLoaded('approver', fn (): ?array => Summary::user($this->approver)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_method' => $this->payment_method?->value,
            'payment_reference' => $this->payment_reference,
            'cash_session_id' => $this->cash_session_id,
            'journal_entry_id' => $this->journal_entry_id,
            'attachments_count' => $this->whenLoaded('attachments', fn (): int => $this->attachments->count()),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
