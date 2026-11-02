<?php

declare(strict_types=1);

namespace App\Dtos\Transfer;

/**
 * A finished export file (`path` on the private disk) or, for large exports, the background task producing it.
 */
final readonly class ExportResult
{
    public function __construct(
        public ?string $path = null,
        public ?string $downloadName = null,
        public ?string $mimeType = null,
        public ?string $taskId = null,
        public int $rows = 0,
    ) {}

    public function queued(): bool
    {
        return $this->taskId !== null;
    }
}
