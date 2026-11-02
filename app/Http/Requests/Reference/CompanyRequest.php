<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Dtos\Reference\AddressData;
use App\Dtos\Reference\CompanyData;
use App\Enums\Reference\CompanyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST (required fields) and PUT (`sometimes`) of a company with its nested address.
 */
final class CompanyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'type' => [$required, Rule::enum(CompanyType::class)],
            'name' => [$required, 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'country_code' => [$required, 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'cr_number' => ['nullable', 'string', 'max:30'],
            ...AddressData::rules('address'),
        ];
    }

    public function toData(): CompanyData
    {
        return CompanyData::fromArray($this->validated());
    }
}
