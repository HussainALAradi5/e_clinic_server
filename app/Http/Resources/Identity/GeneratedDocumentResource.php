<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\GeneratedDocument;
use App\Services\Shared\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generated PDF: its DOC code, unique file name and size, the API paths to open or download it, and short-lived
 * signed links (no sign-in needed while valid) for apps that hand the file to the device's own viewer.
 *
 * @mixin GeneratedDocument
 */
final class GeneratedDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $path = "documents/{$this->type->value}/{$this->record_id}/file";
        $files = app(FileStorage::class);
        $open = $files->temporaryUrl($this->attachment->path, $this->file_name, inline: true);
        $download = $files->temporaryUrl($this->attachment->path, $this->file_name);

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
            'open_url' => $open['url'],
            'download_url' => $download['url'],
            'links_expire_at' => $open['expires_at'],
        ];
    }
}
