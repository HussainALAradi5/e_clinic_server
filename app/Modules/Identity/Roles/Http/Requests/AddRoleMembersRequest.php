<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddRoleMembersRequest extends FormRequest
{
    /** People added in one go (one page of the picker). */
    public const MAX_USERS = 50;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_USERS],
            'user_ids.*' => ['uuid', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return list<string>
     */
    public function userIds(): array
    {
        /** @var list<string> */
        return array_values($this->validated('user_ids'));
    }
}
