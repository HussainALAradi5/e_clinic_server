<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmTwoFactorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
    }
}
