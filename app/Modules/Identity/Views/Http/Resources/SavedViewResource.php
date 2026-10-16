<?php

declare(strict_types=1);

namespace App\Modules\Identity\Views\Http\Resources;

use App\Modules\Identity\Views\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedView
 */
final class SavedViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource' => $this->resource->getAttribute('resource'),
            'name' => $this->name,
            'query' => (object) $this->query,
            'columns' => $this->columns,
            'is_shared' => $this->is_shared,
            'is_default' => $this->is_default,
            'is_mine' => $request->user()?->getAuthIdentifier() === $this->user_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
