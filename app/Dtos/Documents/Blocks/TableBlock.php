<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Interfaces\Documents\DocumentBlock;

/**
 * A table with a coloured header and striped rows; `numeric` columns are right-aligned. An empty heading draws none
 * (list exports, where the page title says it all).
 */
final readonly class TableBlock implements DocumentBlock
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     * @param  list<int>  $numeric  column indexes
     */
    public function __construct(
        public string $heading,
        public array $headers,
        public iterable $rows,
        public array $numeric = [],
        public string $empty = 'Nothing to show.',
    ) {}

    public function view(): string
    {
        return 'pdf.blocks.table';
    }
}
