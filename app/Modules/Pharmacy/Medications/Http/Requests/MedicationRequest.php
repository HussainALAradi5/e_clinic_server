<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Http\Requests;

use App\Enums\Pharmacy\DispenseUnit;
use App\Enums\Pharmacy\MedicationForm;
use App\Enums\Reference\CompanyType;
use App\Modules\Pharmacy\Medications\Data\MedicationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST (required fields) and PUT (`sometimes`) of a medication; price and VAT go to its price-list service.
 */
final class MedicationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $id = $this->route('medication');

        return [
            'code' => [$required, 'string', 'alpha_dash:ascii', 'max:30', Rule::unique('medications', 'code')->ignore(is_string($id) ? $id : null)],
            'generic_name' => [$required, 'string', 'max:150'],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'form' => [$required, Rule::enum(MedicationForm::class)],
            'strength' => [$required, 'string', 'max:50'],
            'unit' => [$required, Rule::enum(DispenseUnit::class)],
            'gtin' => ['nullable', 'digits_between:8,14', Rule::unique('medications', 'gtin')->ignore(is_string($id) ? $id : null)],
            'manufacturer_id' => ['nullable', 'uuid', Rule::exists('companies', 'id')->where('type', CompanyType::Manufacturer->value)->where('is_deleted', 0)],
            'origin_country_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [$required, 'decimal:0,3', 'min:0'],
            'tax_rate' => [$required, 'numeric', 'between:0,100'],
            'requires_prescription' => ['sometimes', 'boolean'],
            'is_controlled' => ['sometimes', 'boolean'],
            'reorder_level' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'storage_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): MedicationData
    {
        return MedicationData::fromArray($this->validated());
    }
}
