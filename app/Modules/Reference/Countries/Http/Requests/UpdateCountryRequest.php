<?php

declare(strict_types=1);

namespace App\Modules\Reference\Countries\Http\Requests;

use App\Modules\Reference\Countries\Data\CountryData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCountryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:100'],
            'nationality' => ['sometimes', 'required', 'string', 'max:100'],
            'nationality_ar' => ['nullable', 'string', 'max:100'],
            'phone_code' => ['sometimes', 'required', 'string', 'regex:/^\+\d{1,5}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    public function toData(): CountryData
    {
        return new CountryData($this->validated());
    }
}
