<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Dtos\Documents\DocumentTemplateData;
use App\Enums\Documents\WrittenDocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A template written from scratch (POST) or changed (PUT; only the given fields). The body is rich text (HTML).
 */
final class DocumentTemplateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'kind' => [$required, Rule::enum(WrittenDocumentKind::class)],
            'body' => [$required, 'string', 'max:60000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): DocumentTemplateData
    {
        return new DocumentTemplateData($this->validated());
    }
}
