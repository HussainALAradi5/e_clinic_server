<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\StartUploadData;
use App\Enums\Identity\AttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'category' => ['required', Rule::enum(AttachmentCategory::class)],
            'sha256' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }

    public function toData(): StartUploadData
    {
        return StartUploadData::fromArray($this->validated());
    }
}
