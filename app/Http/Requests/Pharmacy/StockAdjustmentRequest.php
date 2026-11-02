<?php

declare(strict_types=1);

namespace App\Http\Requests\Pharmacy;

use App\Dtos\Pharmacy\StockAdjustmentData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Count correction of a lot (signed quantity, never zero).
 */
final class StockAdjustmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'decimal:0,2', 'not_in:0,0.0,0.00', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:255'],
            'approved_by' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): StockAdjustmentData
    {
        return StockAdjustmentData::fromArray($this->validated());
    }
}
