<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\GeneratedDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generated PDF: its DOC code, unique file name and size, and the paths to open or download it.
 *
 * @mixin GeneratedDocument
 */
final class GeneratedDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $path = "documents/{$this->type->value}/{$this->record_id}/file";

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'record_id' => $this->record_id,
            'reference' => $this->reference,
            'file_name' => $this->file_name,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toIso8601String(),
            'open_path' => $path,
            'download_path' => $path.'?download=1',
        ];
    }
}
