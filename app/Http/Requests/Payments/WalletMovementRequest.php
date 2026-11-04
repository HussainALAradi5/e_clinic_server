<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Dtos\Payments\WalletMovementData;
use App\Enums\Shared\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A patient balance movement: deposit or pay-back (`method` required), adjustment (`direction` credit / debit).
 * The service applies the balance rules (minimums, limits, allowed methods, reasons).
 */
final class WalletMovementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adjustment = $this->routeIs('billing.wallet.adjust');

        return [
            'amount' => ['required', 'decimal:0,3', 'gt:0', 'max:100000'],
            'method' => [$adjustment ? 'prohibited' : 'required', Rule::enum(PaymentMethod::class)],
            'direction' => [$adjustment ? 'required' : 'prohibited', 'in:credit,debit'],
            'reference_number' => [Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::BankTransfer->value), 'nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): WalletMovementData
    {
        return new WalletMovementData(
            (string) $this->input('amount'),
            PaymentMethod::tryFrom((string) $this->input('method')),
            $this->input('reference_number'),
            $this->input('reason'),
        );
    }

    public function isCredit(): bool
    {
        return $this->input('direction') === 'credit';
    }
}
