<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Http\Resources;

use App\Modules\Identity\Attachments\Models\UploadSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UploadSession
 */
final class UploadSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'category' => $this->category->value,
            'size_bytes' => $this->size_bytes,
            'chunk_size' => $this->chunk_size,
            'received_bytes' => $this->received_bytes,
            'status' => $this->status->value,
            'attachment_id' => $this->attachment_id,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
