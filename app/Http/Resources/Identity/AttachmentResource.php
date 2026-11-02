<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
final class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'category' => $this->category->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'scan_status' => $this->scan_status->value,
            'is_sensitive' => $this->is_sensitive,
            'description' => $this->description,
            'duplicate' => ! $this->wasRecentlyCreated && $request->isMethod('POST'),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
