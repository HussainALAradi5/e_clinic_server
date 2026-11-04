<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Enums\Documents\DocumentType;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\Identity\GeneratedDocumentResource;
use App\Interfaces\Documents\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Branded PDFs of records, one pair of endpoints for every DocumentType.
 */
final class DocumentController
{
    use ApiResponse;

    public function __construct(private readonly DocumentServiceInterface $documents) {}

    /** The record's current document: DOC code, file name, size and where to open or download it. */
    public function show(DocumentType $type, string $id): JsonResponse
    {
        return $this->ok(GeneratedDocumentResource::make($this->documents->generate($type, $id))->resolve());
    }

    /** The PDF itself; opens in the viewer, or saves with `?download=1`. */
    public function file(Request $request, DocumentType $type, string $id): Response
    {
        $file = $this->documents->pdf($type, $id);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($file->content, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$file->fileName.'"',
            'Cache-Control' => 'private, no-store',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }
}
