<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\ChangePasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    public function toData(): ChangePasswordData
    {
        return new ChangePasswordData($this->string('current_password')->toString(), $this->string('password')->toString());
    }
}
