<?php

declare(strict_types=1);

namespace App\Http\Resources\Documents;

use App\Models\Documents\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document template; `is_editable` is false for ready-made ones (copy them to change).
 *
 * @mixin DocumentTemplate
 */
final class DocumentTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind->value,
            'body' => $this->body,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'is_editable' => ! $this->is_system && $this->created_by === $request->user()?->getAuthIdentifier(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
