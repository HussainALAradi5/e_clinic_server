<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }

    public function toData(): ResetPasswordData
    {
        return new ResetPasswordData(
            $this->string('token')->toString(),
            $this->string('email')->toString(),
            $this->string('password')->toString(),
        );
    }
}
