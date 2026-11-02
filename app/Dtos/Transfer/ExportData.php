<?php

declare(strict_types=1);

namespace App\Dtos\Transfer;

use App\Enums\Shared\ExportFormat;

/**
 * Export request: the selected `ids`, or every row matching `filter` (ListQuery grammar, the list on screen),
 * in the given columns (default: all list columns).
 */
final readonly class ExportData
{
    /**
     * @param  list<string>|null  $ids
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $columns
     */
    public function __construct(
        public ExportFormat $format,
        public ?array $ids = null,
        public array $filter = [],
        public array $columns = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input or stored task parameters
     */
    public static function fromArray(array $input): self
    {
        return new self(
            format: ExportFormat::from((string) $input['format']),
            ids: isset($input['ids']) ? array_values(array_unique(array_map(strval(...), (array) $input['ids']))) : null,
            filter: (array) ($input['filter'] ?? []),
            columns: array_values(array_map(strval(...), (array) ($input['columns'] ?? []))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['format' => $this->format->value, 'ids' => $this->ids, 'filter' => $this->filter, 'columns' => $this->columns];
    }
}
