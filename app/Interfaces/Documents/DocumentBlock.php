<?php

declare(strict_types=1);

namespace App\Interfaces\Documents;

/**
 * One part of a PDF page (facts, text, table, totals, images, notice, signature), drawn by its own Blade partial.
 */
interface DocumentBlock
{
    /** Blade partial under `resources/views/pdf/blocks`. */
    public function view(): string;
}
