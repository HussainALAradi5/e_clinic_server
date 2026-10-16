<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Http\Requests;

use App\Modules\Identity\Roles\Data\RoleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'alpha_dash:ascii', 'lowercase', 'max:100', Rule::unique('roles', 'name')->ignore(is_string($role) ? $role : null)],
            'display_name' => ['sometimes', 'required', 'string', 'max:150'],
            'display_name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')],
        ];
    }

    public function toData(): RoleData
    {
        return RoleData::fromArray($this->validated());
    }
}
