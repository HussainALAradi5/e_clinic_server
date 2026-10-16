<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Requests;

use App\Enums\Identity\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeUserStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['nullable', 'required_if:status,locked,inactive', 'string', 'max:500'],
        ];
    }

    public function status(): UserStatus
    {
        return UserStatus::from($this->string('status')->toString());
    }
}
