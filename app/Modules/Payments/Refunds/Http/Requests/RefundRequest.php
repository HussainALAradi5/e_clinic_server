<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Http\Requests;

use App\Modules\Payments\Refunds\Data\RefundRequestData;
use App\Shared\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Refund request against one payment; the pay-out method defaults to the payment's own.
 */
final class RefundRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'uuid', Rule::exists('payments', 'id')->where('is_deleted', 0)],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Insurance])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function toData(): RefundRequestData
    {
        return RefundRequestData::fromArray($this->validated());
    }
}
