<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Dtos\Documents\DocumentImage;
use App\Interfaces\Documents\DocumentBlock;

/**
 * A titled gallery (imaging films, ECG strips), two per row.
 */
final readonly class ImagesBlock implements DocumentBlock
{
    /**
     * @param  list<DocumentImage>  $images
     */
    public function __construct(public string $heading, public array $images) {}

    public function view(): string
    {
        return 'pdf.blocks.images';
    }
}
