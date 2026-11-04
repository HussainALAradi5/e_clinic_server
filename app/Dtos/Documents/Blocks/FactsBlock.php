<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Dtos\Documents\DocumentFact;
use App\Interfaces\Documents\DocumentBlock;

/**
 * A titled card of label / value pairs in two columns (patient, doctor, dates).
 */
final readonly class FactsBlock implements DocumentBlock
{
    /** @var list<DocumentFact> */
    public array $facts;

    /**
     * @param  list<DocumentFact|null>  $facts  nulls (empty optional facts) are dropped
     */
    public function __construct(public string $heading, array $facts)
    {
        $this->facts = array_values(array_filter($facts));
    }

    public function view(): string
    {
        return 'pdf.blocks.facts';
    }
}
