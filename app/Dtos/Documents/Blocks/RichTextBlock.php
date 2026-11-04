<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Interfaces\Documents\DocumentBlock;
use App\Utilities\Documents\RichText;

/**
 * Rich text written in the document editor (bold, italic, headings, lists…), cleaned again before printing.
 */
final readonly class RichTextBlock implements DocumentBlock
{
    public string $html;

    public function __construct(string $html)
    {
        $this->html = RichText::sanitize($html);
    }

    public function view(): string
    {
        return 'pdf.blocks.rich-text';
    }
}
