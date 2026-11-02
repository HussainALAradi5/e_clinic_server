<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use App\Enums\Shared\FilterFieldType;
use App\Enums\Shared\FilterOperator;
use Closure;
use Illuminate\Support\Str;

/**
 * Per-endpoint whitelist for the generic list query. Also the single source of the client's resource schema.
 *
 * Date fields ending in `_at` are treated as timestamps (clinic-local day → UTC range), all others as dates.
 */
final class ListQueryConfig
{
    /** @var list<string> */
    private array $sortable = [];

    /** @var array<string, FilterField> */
    private array $filters = [];

    /** @var list<string> */
    private array $dateFields = [];

    /** @var list<string> */
    private array $searchColumns = [];

    /** @var array<string, list<string>> relation path => its columns, searched through whereHas */
    private array $searchRelations = [];

    /** @var list<string> */
    private array $includes = [];

    /** @var array<string, string> field => label */
    private array $columns = [];

    private string $defaultSort = '-created_at';

    private ?int $maxDateRangeDays = null;

    private bool $cursor = false;

    private ?string $resource = null;

    public static function make(): self
    {
        return new self;
    }

    public function sortable(string ...$fields): self
    {
        $this->sortable = array_values(array_unique([...$this->sortable, ...$fields]));

        return $this;
    }

    /**
     * @param  list<string|FilterOperator>  $operators
     * @param  class-string<\BackedEnum>|null  $enum
     * @param  (Closure(): array<string, string>)|null  $options  named choices (FilterOptions) instead of typing a code
     */
    public function filterable(
        string $field,
        array $operators = ['eq', 'in'],
        ?string $column = null,
        FilterFieldType|string|null $type = null,
        ?string $enum = null,
        ?string $resource = null,
        ?string $label = null,
        bool $hidden = false,
        ?Closure $options = null,
    ): self {
        $type = match (true) {
            $type instanceof FilterFieldType => $type,
            is_string($type) => FilterFieldType::from($type),
            $enum !== null => FilterFieldType::Enum,
            $options !== null => FilterFieldType::Choice,
            $resource !== null => FilterFieldType::Relation,
            default => FilterFieldType::Text,
        };

        $this->filters[$field] = new FilterField(
            field: $field,
            operators: array_map(fn (string|FilterOperator $op): FilterOperator => $op instanceof FilterOperator ? $op : FilterOperator::from($op), $operators),
            column: $column ?? $field,
            type: $type,
            enum: $enum,
            resource: $resource,
            label: $label,
            hidden: $hidden,
            options: $options,
        );

        return $this;
    }

    public function dateFields(string $default, string ...$others): self
    {
        $this->dateFields = array_values(array_unique([$default, ...$others]));

        return $this;
    }

    /**
     * Text the search box should also find that is not a column of the table (a phone number, a reference).
     * Text columns and related records shown in the table are searched anyway (SearchScope).
     */
    public function searchIn(string ...$columns): self
    {
        $this->searchColumns = array_values($columns);

        return $this;
    }

    /**
     * Also search columns of a related record, e.g. `searchInRelation('patient.person', 'first_name', 'phone')`
     * (an invoice found by its patient's name) — no join, so the list's own columns stay unambiguous.
     */
    public function searchInRelation(string $relation, string ...$columns): self
    {
        $this->searchRelations[$relation] = array_values($columns);

        return $this;
    }

    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function maxDateRangeDays(int $days): self
    {
        $this->maxDateRangeDays = $days;

        return $this;
    }

    /** Relations the client may eager-load with `include=`. */
    public function includes(string ...$relations): self
    {
        $this->includes = array_values($relations);

        return $this;
    }

    /** Default table columns offered by the resource schema. */
    public function column(string $field, string $label): self
    {
        $this->columns[$field] = $label;

        return $this;
    }

    /** Append-only lists (audit logs, money movements) use cursor pagination. */
    public function cursorPagination(bool $enabled = true): self
    {
        $this->cursor = $enabled;

        return $this;
    }

    /** Permission resource name (e.g. `rooms`); `only_deleted=true` requires `<resource>.delete`. */
    public function resource(string $name): self
    {
        $this->resource = $name;

        return $this;
    }

    public function resourceName(): ?string
    {
        return $this->resource;
    }

    public function isSortable(string $field): bool
    {
        return $field === 'id' || in_array($field, $this->sortable, true);
    }

    /**
     * `id` is always filterable with eq / in / nin (bulk "select all matching" excludes unticked rows with id nin).
     */
    public function filter(string $field): ?FilterField
    {
        return $this->filters[$field]
            ?? ($field === 'id' ? new FilterField('id', [FilterOperator::Eq, FilterOperator::In, FilterOperator::Nin], 'id') : null);
    }

    /** @return array<string, FilterField> */
    public function filters(): array
    {
        return $this->filters;
    }

    /** @return list<string> */
    public function sortableFields(): array
    {
        return $this->sortable;
    }

    /** @return list<string> */
    public function dateFieldNames(): array
    {
        return $this->dateFields;
    }

    public function defaultDateField(): ?string
    {
        return $this->dateFields[0] ?? null;
    }

    /** A search box makes sense: there are columns to look in (SearchScope works out which). */
    public function searchable(): bool
    {
        return $this->searchColumns !== [] || $this->searchRelations !== [] || $this->columns !== [];
    }

    /**
     * Extra text the search box looks in besides the table's columns (see SearchScope).
     *
     * @return list<string>
     */
    public function searchColumns(): array
    {
        return $this->searchColumns;
    }

    /** @return array<string, list<string>> */
    public function searchRelations(): array
    {
        return $this->searchRelations;
    }

    /** @return list<string> */
    public function includeNames(): array
    {
        return $this->includes;
    }

    /** @return array<string, string> */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Offered in the filter panel? Not internal ids (nobody types an id; links still use them), and not a text filter
     * on a column the search box already covers (e.g. the code — a second "starts with" box would only repeat Search).
     */
    public function filterHidden(FilterField $filter): bool
    {
        $text = $filter->type === FilterFieldType::Text;

        return $filter->hidden
            || ($text && str_ends_with($filter->column, '_id'))
            || ($text && (in_array($filter->column, $this->searchColumns, true) || isset($this->columns[$filter->field])));
    }

    /**
     * What a filter is called on screen: its own label, else the label of its column (`provider_id` → the `provider`
     * column), else the field in words. Users never see field names.
     */
    public function filterLabel(FilterField $filter): string
    {
        $base = preg_replace('/_id$/', '', $filter->field) ?? $filter->field;

        return $filter->label ?? $this->columns[$filter->field] ?? $this->columns[$base] ?? Str::ucfirst(str_replace('_', ' ', $base));
    }

    public function defaultSortValue(): string
    {
        return $this->defaultSort;
    }

    public function maxDateRange(): ?int
    {
        return $this->maxDateRangeDays;
    }

    public function usesCursor(): bool
    {
        return $this->cursor;
    }

    /** Real column for a filter / sort / date field. */
    public function columnFor(string $field): string
    {
        return $this->filters[$field]->column ?? $field;
    }

    public static function isTimestampField(string $field): bool
    {
        return str_ends_with($field, '_at');
    }
}
