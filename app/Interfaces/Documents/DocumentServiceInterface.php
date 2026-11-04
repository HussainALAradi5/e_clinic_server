<?php

declare(strict_types=1);

namespace App\Interfaces\Documents;

use App\Dtos\Documents\RenderedFile;
use App\Enums\Documents\DocumentType;
use App\Models\Identity\GeneratedDocument;

interface DocumentServiceInterface
{
    /** The record's current PDF (made now, or reused while the record is unchanged); 404 when it may not be read. */
    public function generate(DocumentType $type, string $id): GeneratedDocument;

    /** The record's current PDF bytes and file name. */
    public function pdf(DocumentType $type, string $id): RenderedFile;

    /**
     * Variables of the shared letterhead for a page titled `$title` with `$reference`.
     *
     * @return array<string, mixed>
     */
    public function letterhead(string $title, string $reference): array;
}
