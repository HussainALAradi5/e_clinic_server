<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Dtos\Documents\WrittenDocumentData;
use App\Enums\Documents\WrittenDocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A document written at the visit: kind, title, rich-text body and the template it started from.
 */
final class WrittenDocumentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(WrittenDocumentKind::class)],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:60000'],
            'template_id' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): WrittenDocumentData
    {
        return new WrittenDocumentData(
            WrittenDocumentKind::from($this->string('kind')->toString()),
            $this->string('title')->toString(),
            $this->string('body')->toString(),
            $this->input('template_id'),
        );
    }
}
