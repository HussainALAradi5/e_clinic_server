<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\LoginData;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
            'trusted_device_token' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function toData(): LoginData
    {
        return new LoginData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            deviceName: $this->string('device_name')->toString(),
            ip: (string) $this->ip(),
            userAgent: $this->userAgent(),
            trustedDevice: $this->filled('trusted_device_token') ? $this->string('trusted_device_token')->toString() : null,
        );
    }
}
