<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Dtos\Appointments\RoomData;
use App\Enums\Appointments\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RoomRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'alpha_dash:ascii', 'max:20'],
            'name' => [$required, 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'type' => [$required, Rule::enum(RoomType::class)],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where('is_deleted', 0)],
            'floor' => ['nullable', 'string', 'max:10'],
            'capacity' => [$required, 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): RoomData
    {
        return RoomData::fromArray($this->validated());
    }
}
