<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\AttachToData;
use App\Enums\Identity\AttachmentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Multipart `files[]` (1–20) + owner + category. Per-file type / size rules are applied by the service so valid
 * files are saved even when others fail.
 */
final class UploadAttachmentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
            'attachable_type' => ['required', 'string', Rule::in(array_keys((array) config('attachments.owners')))],
            'attachable_id' => ['required', 'uuid'],
            'category' => ['required', Rule::enum(AttachmentCategory::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return array_values(array_filter((array) $this->file('files'), fn ($f): bool => $f instanceof UploadedFile));
    }

    public function owner(): AttachToData
    {
        return AttachToData::fromArray($this->validated());
    }
}
