<?php

declare(strict_types=1);

namespace App\Utilities\Shared;

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Models\Identity\Attachment;
use App\Services\Shared\FileStorage;
use Illuminate\Http\UploadedFile;

/**
 * Saves a small validated image (clinic logo or stamp, a signature) as an attachment of its owner record.
 */
final class StoredImage
{
    public function __construct(private readonly FileStorage $files) {}

    public function store(UploadedFile $file, string $ownerType, string $ownerId, AttachmentCategory $category, string $folder): Attachment
    {
        $bytes = (string) $file->get();
        $mime = (string) $file->getMimeType();
        $path = $this->files->newPath($folder, $mime === 'image/png' ? 'png' : 'jpg');
        $this->files->disk()->put($path, $bytes);

        $attachment = (new Attachment)->forceFill([
            'attachable_type' => $ownerType,
            'attachable_id' => $ownerId,
            'category' => $category,
            'original_name' => mb_substr($file->getClientOriginalName() ?: $category->value.'.png', 0, 255),
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'disk' => $this->files->diskName(),
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'scan_status' => FileScanStatus::Clean,
            'is_sensitive' => $category === AttachmentCategory::Signature,
        ]);
        $attachment->save();

        return $attachment;
    }
}
