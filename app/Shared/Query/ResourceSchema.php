<?php

declare(strict_types=1);

namespace App\Shared\Query;

/**
 * What the client needs to build filters, columns, bulk menus and export for one resource (GET /meta/resources/{resource}).
 */
final readonly class ResourceSchema
{
    /**
     * @param  list<array{field: string, type: string, operators: list<string>, enum?: string, resource?: string}>  $filters
     * @param  list<string>  $sortable
     * @param  list<string>  $dateFields
     * @param  list<array{field: string, label: string}>  $columns
     * @param  list<array{name: string, needs_reason: bool, inverse: ?string}>  $bulkActions
     * @param  list<string>  $exportFormats  formats the caller may export (empty = no export)
     */
    public function __construct(
        public string $resource,
        public array $filters,
        public array $sortable,
        public bool $search,
        public array $dateFields,
        public array $columns,
        public array $bulkActions,
        public array $exportFormats,
        public string $defaultSort,
        public bool $archivable = false,
        public bool $importable = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'filters' => $this->filters,
            'sortable' => $this->sortable,
            'search' => $this->search,
            'date_fields' => $this->dateFields,
            'columns' => $this->columns,
            'bulk_actions' => $this->bulkActions,
            'export_formats' => $this->exportFormats,
            'default_sort' => $this->defaultSort,
            // GenericEntity resources: the list offers "show archived".
            'archivable' => $this->archivable,
            // The caller may import this list from Excel (template at GET /<resource>/import/template).
            'importable' => $this->importable,
        ];
    }
}
