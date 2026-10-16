<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DisableTwoFactorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'max:255']];
    }
}
