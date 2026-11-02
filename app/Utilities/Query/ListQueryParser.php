<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use App\Enums\Shared\DateRangePreset;
use App\Enums\Shared\FilterFieldType;
use App\Enums\Shared\FilterOperator;
use App\Enums\Shared\LogicalOperator;
use App\Enums\Shared\SortDirection;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Turns raw query parameters into a ListQuery, collecting validation errors instead of throwing on the first one.
 *
 * @internal used by ListQuery::fromArray()
 */
final class ListQueryParser
{
    /** @var array<string, list<string>> */
    public array $errors = [];

    private int $conditions = 0;

    public function __construct(
        private readonly ListQueryConfig $config,
        private readonly ClinicClock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function parse(array $params): ListQuery
    {
        return new ListQuery(
            config: $this->config,
            page: $this->integer($params, 'page', 1, 1, PHP_INT_MAX),
            perPage: $this->integer($params, 'per_page', Config::integer('clinic.list.default_per_page'), 1, Config::integer('clinic.list.max_per_page')),
            cursor: is_string($params['cursor'] ?? null) && $params['cursor'] !== '' ? $params['cursor'] : null,
            sort: $this->sort($params),
            date: $this->date($params),
            filter: $this->rootFilter($params['filter'] ?? null),
            search: $this->search($params['search'] ?? null),
            includeArchived: $this->boolean($params, 'include_archived'),
            onlyDeleted: $this->boolean($params, 'only_deleted'),
            include: $this->include($params['include'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function integer(array $params, string $key, int $default, int $min, int $max): int
    {
        if (! isset($params[$key]) || $params[$key] === '') {
            return $default;
        }

        $value = filter_var($params[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $this->error($key, __('validation.between.numeric', ['attribute' => $key, 'min' => $min, 'max' => $max]));

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function boolean(array $params, string $key): bool
    {
        if (! isset($params[$key])) {
            return false;
        }

        $value = self::toBool($params[$key]);
        if ($value === null) {
            $this->error($key, __('validation.boolean', ['attribute' => $key]));
        }

        return $value ?? false;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<SortField>
     */
    private function sort(array $params): array
    {
        if (is_string($params['sort'] ?? null) && $params['sort'] !== '') {
            $fields = array_map(SortField::fromCompact(...), array_filter(array_map('trim', explode(',', $params['sort']))));
        } elseif (is_string($params['sort_by'] ?? null) && $params['sort_by'] !== '') {
            $dir = SortDirection::tryFrom((string) ($params['sort_dir'] ?? 'asc'));
            if ($dir === null) {
                $this->error('sort_dir', __('validation.in', ['attribute' => 'sort_dir']));
            }
            $fields = [new SortField($params['sort_by'], $dir ?? SortDirection::Asc)];
        } else {
            return array_map(SortField::fromCompact(...), explode(',', $this->config->defaultSortValue()));
        }

        foreach ($fields as $field) {
            if (! $this->config->isSortable($field->field)) {
                $this->error('sort', __('list_query.not_sortable', ['field' => $field->field]));
            }
        }

        return array_values($fields);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function date(array $params): ?DateFilter
    {
        $from = $params['date_from'] ?? $params['from'] ?? null;
        $to = $params['date_to'] ?? $params['to'] ?? null;
        $presetValue = $params['date_range'] ?? null;
        // One parameter for fixed days too: `date_range=2026-10-01..2026-10-22` (either side may be left open).
        if (is_string($presetValue) && str_contains($presetValue, '..')) {
            [$from, $to] = array_map(fn (string $d): ?string => $d === '' ? null : $d, explode('..', $presetValue, 2));
            $presetValue = null;
        }

        if ($from === null && $to === null && $presetValue === null) {
            return null;
        }

        $field = (string) ($params['date_field'] ?? $this->config->defaultDateField());
        if (! in_array($field, $this->config->dateFieldNames(), true)) {
            $this->error('date_field', __('list_query.not_date_field', ['field' => $field]));

            return null;
        }

        $preset = null;
        if ($presetValue !== null) {
            $preset = DateRangePreset::tryFrom((string) $presetValue);
            if ($preset === null) {
                $this->error('date_range', __('validation.in', ['attribute' => 'date_range']));

                return null;
            }
        }

        $range = $preset?->range($this->clock->today());
        if ($range !== null) {
            return new DateFilter($field, $range[0], $range[1], $preset);
        }

        $fromDate = $this->parseDate('date_from', $from);
        $toDate = $this->parseDate('date_to', $to);

        if ($fromDate && $toDate) {
            if ($fromDate->gt($toDate)) {
                $this->error('date_to', __('validation.after_or_equal', ['attribute' => 'date_to', 'date' => 'date_from']));
            }

            $max = $this->config->maxDateRange();
            if ($max !== null && $fromDate->diffInDays($toDate) + 1 > $max) {
                $this->error('date_to', __('list_query.date_range_too_large', ['days' => $max]));
            }
        }

        return new DateFilter($field, $fromDate, $toDate, $preset);
    }

    private function parseDate(string $key, mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = is_string($value) && CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m-d') ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
        if ($date === null || $date->format('Y-m-d') !== $value) {
            $this->error($key, __('validation.date_format', ['attribute' => $key, 'format' => 'YYYY-MM-DD']));

            return null;
        }

        return $date;
    }

    private function rootFilter(mixed $filter): ?FilterGroup
    {
        if ($filter === null || $filter === [] || $filter === '') {
            return null;
        }

        if (! is_array($filter)) {
            $this->error('filter', __('validation.array', ['attribute' => 'filter']));

            return null;
        }

        $group = $this->group(LogicalOperator::And, $filter, 1, 'filter');

        return $group->children === [] ? null : $group;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function group(LogicalOperator $op, array $node, int $depth, string $path): FilterGroup
    {
        if ($depth > ListQuery::MAX_DEPTH) {
            $this->error('filter', __('list_query.too_deep', ['max' => ListQuery::MAX_DEPTH]));

            return new FilterGroup($op, []);
        }

        $children = [];
        foreach ($node as $key => $value) {
            $key = (string) $key;
            $logical = LogicalOperator::tryFrom($key);

            if ($logical !== null) {
                if (! is_array($value)) {
                    $this->error("{$path}.{$key}", __('validation.array', ['attribute' => $key]));

                    continue;
                }

                $children[] = $logical === LogicalOperator::Not
                    ? new FilterGroup(LogicalOperator::Not, [$this->group(LogicalOperator::And, $value, $depth + 1, "{$path}.not")])
                    : new FilterGroup($logical, array_map(
                        fn (mixed $item, int|string $i): FilterGroup => $this->group(LogicalOperator::And, is_array($item) ? $item : [], $depth + 1, "{$path}.{$key}.{$i}"),
                        $value,
                        array_keys($value),
                    ));

                continue;
            }

            array_push($children, ...$this->conditions($key, $value, "{$path}.{$key}"));
        }

        return new FilterGroup($op, $children);
    }

    /**
     * `filter[status]=a` (eq) or `filter[total][gte]=10&filter[total][lte]=50`.
     *
     * @return list<FilterCondition>
     */
    private function conditions(string $field, mixed $value, string $path): array
    {
        $definition = $this->config->filter($field);
        if ($definition === null) {
            $this->error($path, __('list_query.not_filterable', ['field' => $field]));

            return [];
        }

        $pairs = is_array($value) && ! array_is_list($value) ? $value : ['eq' => $value];
        $conditions = [];

        foreach ($pairs as $opValue => $operand) {
            $op = FilterOperator::tryFrom((string) $opValue);
            if ($op === null || ! $definition->allows($op)) {
                $this->error($path, __('list_query.operator_not_allowed', ['field' => $field, 'operator' => $opValue]));

                continue;
            }

            if (++$this->conditions > ListQuery::MAX_CONDITIONS) {
                $this->error('filter', __('list_query.too_many_conditions', ['max' => ListQuery::MAX_CONDITIONS]));

                return $conditions;
            }

            $normalised = $this->operand($op, $operand, $definition, $path);
            if ($normalised !== null) {
                $conditions[] = new FilterCondition($field, $op, $normalised);
            }
        }

        return $conditions;
    }

    private function operand(FilterOperator $op, mixed $operand, FilterField $definition, string $path): mixed
    {
        if ($op === FilterOperator::Null) {
            $bool = self::toBool($operand);
            if ($bool === null) {
                $this->error($path, __('validation.boolean', ['attribute' => $definition->field]));
            }

            return $bool;
        }

        if ($op->expectsList()) {
            $list = is_array($operand) ? array_values($operand) : explode(',', (string) $operand);
            $list = array_map(fn (mixed $item): mixed => $this->scalar($item, $definition), $list);

            if ($op === FilterOperator::Between && count($list) !== 2) {
                $this->error($path, __('list_query.between_needs_two', ['field' => $definition->field]));

                return null;
            }

            return $list;
        }

        if (is_array($operand)) {
            $this->error($path, __('validation.string', ['attribute' => $definition->field]));

            return null;
        }

        return $this->scalar($operand, $definition);
    }

    private function scalar(mixed $value, FilterField $definition): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        return $definition->type === FilterFieldType::Boolean ? (self::toBool($value) ?? $value) : $value;
    }

    private function search(mixed $search): ?string
    {
        if (! is_string($search) || trim($search) === '') {
            return null;
        }

        if (! $this->config->searchable()) {
            $this->error('search', __('list_query.search_not_supported'));

            return null;
        }

        return mb_substr(trim($search), 0, ListQuery::MAX_SEARCH_LENGTH);
    }

    /**
     * @return list<string>
     */
    private function include(mixed $include): array
    {
        if (! is_string($include) || $include === '') {
            return [];
        }

        $relations = array_values(array_filter(array_map('trim', explode(',', $include))));
        foreach (array_diff($relations, $this->config->includeNames()) as $relation) {
            $this->error('include', __('list_query.not_includable', ['relation' => $relation]));
        }

        return array_values(array_intersect($relations, $this->config->includeNames()));
    }

    private function error(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    private static function toBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
