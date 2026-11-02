<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\AttachToData;
use App\Enums\Identity\AttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in(array_keys((array) config('attachments.owners')))],
            'attachable_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The category was fixed when the upload started (the service replaces this placeholder). */
    public function owner(): AttachToData
    {
        return AttachToData::fromArray($this->validated(), AttachmentCategory::Other);
    }
}
