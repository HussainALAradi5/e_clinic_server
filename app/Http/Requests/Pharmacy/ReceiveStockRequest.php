<?php

declare(strict_types=1);

namespace App\Http\Requests\Pharmacy;

use App\Dtos\Pharmacy\ReceiveStockData;
use App\Enums\Reference\CompanyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Goods receipt of one lot.
 */
final class ReceiveStockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'medication_id' => ['required', 'uuid'],
            'batch_number' => ['required', 'string', 'max:50'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:1000000'],
            'unit_cost' => ['required', 'decimal:0,3', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('companies', 'id')->where('type', CompanyType::Supplier->value)->where('is_deleted', 0)],
            'create_expense' => ['sometimes', 'boolean'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toData(): ReceiveStockData
    {
        return ReceiveStockData::fromArray($this->validated());
    }
}
