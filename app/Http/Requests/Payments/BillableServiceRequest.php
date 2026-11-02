<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Dtos\Payments\BillableServiceData;
use App\Enums\Appointments\ConsentType;
use App\Enums\Payments\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST (required fields) and PUT (`sometimes`) of a price-list item.
 */
final class BillableServiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'alpha_dash:ascii', 'max:30'],
            'name' => [$required, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'category' => [$required, Rule::enum(ServiceCategory::class)],
            'price' => [$required, 'decimal:0,3', 'min:0'],
            'tax_rate' => [$required, 'numeric', 'between:0,100'],
            'revenue_account_id' => [$required, 'uuid'],
            'specialty_id' => ['nullable', 'uuid', Rule::exists('specialties', 'id')->where('is_deleted', 0)],
            'is_insurable' => ['sometimes', 'boolean'],
            'consent_type' => ['nullable', Rule::enum(ConsentType::class)],
        ];
    }

    public function toData(): BillableServiceData
    {
        return BillableServiceData::fromArray($this->validated());
    }
}
