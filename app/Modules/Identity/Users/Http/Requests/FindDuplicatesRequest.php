<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Requests;

use App\Modules\Identity\Users\Data\PersonData;
use Illuminate\Foundation\Http\FormRequest;

final class FindDuplicatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'except_id' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): PersonData
    {
        return PersonData::fromArray(collect($this->validated())->except('except_id')->all());
    }
}
