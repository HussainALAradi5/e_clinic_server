<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\SpecialtyData;
use Illuminate\Foundation\Http\FormRequest;

final class SpecialtyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'alpha_dash:ascii', 'max:20'],
            'name' => [$required, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): SpecialtyData
    {
        return SpecialtyData::fromArray($this->validated());
    }
}
