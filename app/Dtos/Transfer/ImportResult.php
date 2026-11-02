<?php

declare(strict_types=1);

namespace App\Dtos\Transfer;

/**
 * Outcome of an import: every row was saved (all-or-nothing).
 */
final readonly class ImportResult
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(
        public string $resource,
        public int $created,
        public array $ids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['resource' => $this->resource, 'created' => $this->created, 'ids' => $this->ids];
    }
}
