<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use App\Dtos\Reference\ClinicProfileData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClinicProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function toData(): ClinicProfileData
    {
        return new ClinicProfileData($this->validated());
    }
}
