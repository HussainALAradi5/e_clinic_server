<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ZipAttachmentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachment_ids' => ['required', 'array', 'min:1', 'max:'.(int) config('attachments.zip.max_files')],
            'attachment_ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_map('strval', (array) $this->validated('attachment_ids')));
    }
}
