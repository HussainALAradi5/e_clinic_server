<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

use App\Dtos\Documents\Blocks\TableBlock;
use App\Enums\Documents\DocumentLayout;
use App\Interfaces\Documents\DocumentServiceInterface;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Writes rows as a branded PDF table (list exports, financial reports) on the shared letterhead: brand header row
 * repeated on every page, striped rows, landscape paper when there are many columns.
 */
final class TablePdfWriter
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly DocumentServiceInterface $documents,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     * @param  list<int>  $numeric  right-aligned column indexes
     */
    public function write(string $path, string $title, string $subtitle, array $headers, iterable $rows, array $numeric = []): void
    {
        $bytes = $this->renderer->render(DocumentLayout::Table, [
            ...$this->documents->letterhead($title, $subtitle),
            'heading' => $title,
            'subtitle' => $subtitle,
            'table' => new TableBlock('', $headers, $rows, $numeric),
        ], landscape: count($headers) > Config::integer('documents.landscape_after_columns'));

        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Cannot write the PDF file.');
        }
    }
}
