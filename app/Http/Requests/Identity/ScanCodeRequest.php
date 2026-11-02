<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\ScanPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScanCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:200'],
            'purpose' => ['required', Rule::enum(ScanPurpose::class)],
        ];
    }

    public function purpose(): ScanPurpose
    {
        return ScanPurpose::from($this->string('purpose')->toString());
    }
}
