<?php

declare(strict_types=1);

namespace App\Shared\Tasks;

/**
 * Result of a finished task: an optional file on the private disk and a small summary (`{"rows": 1200}`).
 */
final readonly class TaskOutcome
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public ?string $resultPath = null,
        public array $summary = [],
        public ?string $downloadName = null,
    ) {}
}
