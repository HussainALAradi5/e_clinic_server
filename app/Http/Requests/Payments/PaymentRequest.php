<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Dtos\Payments\RecordPaymentData;
use App\Enums\Shared\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One payment against an invoice (a split payment is one request per method). Card and transfer payments carry the
 * terminal / bank reference; insurance settles through claims, never at the desk.
 */
final class PaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $desk = array_values(array_filter(PaymentMethod::cases(), fn (PaymentMethod $m): bool => $m->isDesk()));

        return [
            'invoice_id' => ['required', 'uuid', Rule::exists('invoices', 'id')->where('is_deleted', 0)],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only($desk)],
            'reference_number' => [Rule::requiredIf(fn (): bool => in_array($this->input('method'), [PaymentMethod::BankTransfer->value, PaymentMethod::Cheque->value], true)), 'nullable', 'string', 'max:100'],
            'card_last4' => ['nullable', 'digits:4'],
            'notes' => ['nullable', 'string', 'max:500'],
            'pay_insurer_share' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): RecordPaymentData
    {
        return RecordPaymentData::fromArray($this->validated());
    }
}
