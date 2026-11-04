<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Interfaces\Documents\DocumentBlock;

/**
 * A titled section of paragraphs (report, findings, certificate wording). Empty paragraphs are dropped.
 */
final readonly class TextBlock implements DocumentBlock
{
    /** @var list<string> */
    public array $paragraphs;

    /**
     * @param  list<string|null>  $paragraphs
     */
    public function __construct(public string $heading, array $paragraphs)
    {
        $this->paragraphs = array_values(array_filter($paragraphs, fn (?string $p): bool => $p !== null && trim($p) !== ''));
    }

    public function view(): string
    {
        return 'pdf.blocks.text';
    }
}
