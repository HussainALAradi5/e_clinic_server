<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\RoleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'alpha_dash:ascii', 'lowercase', 'max:100', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:150'],
            'display_name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')],
        ];
    }

    public function toData(): RoleData
    {
        return RoleData::fromArray([...$this->validated(), 'permissions' => $this->validated('permissions', [])]);
    }
}
