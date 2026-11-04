<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

use App\Utilities\Documents\RichText;
use Illuminate\Support\Arr;

/**
 * Editable template fields; only provided keys are applied. The body is cleaned rich text.
 */
final readonly class DocumentTemplateData
{
    /**
     * @param  array<string, mixed>  $values  validated snake_case input
     */
    public function __construct(public array $values) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = Arr::only($this->values, ['name', 'description', 'kind', 'is_active']);
        if (array_key_exists('body', $this->values)) {
            $attributes['body'] = RichText::sanitize((string) $this->values['body']);
        }

        return $attributes;
    }
}
