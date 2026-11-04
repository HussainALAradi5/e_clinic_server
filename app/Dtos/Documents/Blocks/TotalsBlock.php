<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Dtos\Documents\DocumentFact;
use App\Interfaces\Documents\DocumentBlock;

/**
 * Money summary aligned right under a table, ending with the highlighted grand line (total, net pay, balance).
 */
final readonly class TotalsBlock implements DocumentBlock
{
    /** @var list<DocumentFact> */
    public array $lines;

    /**
     * @param  list<DocumentFact|null>  $lines
     */
    public function __construct(array $lines, public DocumentFact $grand)
    {
        $this->lines = array_values(array_filter($lines));
    }

    public function view(): string
    {
        return 'pdf.blocks.totals';
    }
}
