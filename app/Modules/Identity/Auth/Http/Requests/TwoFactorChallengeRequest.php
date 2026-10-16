<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['required_without:recovery_code', 'nullable', 'digits:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** The TOTP code, or the recovery code when no TOTP code was sent. */
    public function code(): string
    {
        return $this->filled('code') ? $this->string('code')->toString() : $this->string('recovery_code')->toString();
    }
}
