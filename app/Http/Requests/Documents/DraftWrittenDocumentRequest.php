<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Enums\Documents\WrittenDocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Auto generate": a template to fill from the visit, or the author's own text with {{placeholders}}.
 */
final class DraftWrittenDocumentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(WrittenDocumentKind::class)],
            'template_id' => ['required_without:body', 'nullable', 'uuid'],
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:60000'],
        ];
    }

    public function kind(): WrittenDocumentKind
    {
        return WrittenDocumentKind::from($this->string('kind')->toString());
    }
}
