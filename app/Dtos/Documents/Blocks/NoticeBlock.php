<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBlock;

/**
 * A coloured call-out (cancelled, abnormal result, revoked consent).
 */
final readonly class NoticeBlock implements DocumentBlock
{
    public function __construct(
        public string $title,
        public string $text,
        public DocumentTone $tone = DocumentTone::Warning,
    ) {}

    public function view(): string
    {
        return 'pdf.blocks.notice';
    }
}
