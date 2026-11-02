<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use App\Dtos\Bulk\BulkAction;
use App\Enums\Shared\ExportFormat;
use App\Enums\Shared\FilterOperator;
use App\Exceptions\Shared\UnknownResourceException;
use App\Interfaces\Shared\GenericEntity;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Transfer\TransferGuard;
use App\Utilities\Access\PermissionExpression;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds the client resource schema from the same ListQueryConfig that validates the list endpoint,
 * filtered by the caller's permissions (unknown or unreadable resource → 404 UNKNOWN_RESOURCE).
 */
final class ResourceSchemaBuilder
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly TransferGuard $transfer,
    ) {}

    public function build(string $resource, Authenticatable $user): ResourceSchema
    {
        if (! $this->registry->has($resource)) {
            throw new UnknownResourceException;
        }

        $definition = $this->registry->for($resource);
        if (! PermissionExpression::parse($definition->readPermission())->evaluate($user)) {
            throw new UnknownResourceException;
        }

        $config = $definition->config;

        return new ResourceSchema(
            resource: $resource,
            filters: array_values(array_map(fn (FilterField $f): array => array_filter([
                'field' => $f->field,
                'label' => $config->filterLabel($f),
                'type' => $f->type->value,
                'operators' => array_map(fn (FilterOperator $op): string => $op->value, $f->operators),
                'enum' => $f->enum !== null ? class_basename($f->enum) : null,
                'resource' => $f->resource,
                'options' => $f->options !== null ? array_map(
                    fn (string|int $value, string $label): array => ['value' => (string) $value, 'label' => $label],
                    array_keys($options = ($f->options)()),
                    $options,
                ) : null,
                // Accepted (links, saved views) but not offered in the filter panel: internal ids, and text the search box covers.
                'hidden' => $config->filterHidden($f) ?: null,
            ], fn (mixed $value): bool => $value !== null), $config->filters())),
            sortable: $config->sortableFields(),
            search: ! ($scope = SearchScope::for(new ($definition->model), $config))->isEmpty(),
            searchHint: $this->searchHint($config, $scope),
            dateFields: $config->dateFieldNames(),
            columns: $this->columns($config, $definition->model),
            bulkActions: array_values(array_map(
                fn (BulkAction $a): array => ['name' => $a->name, 'needs_reason' => $a->needsReason, 'inverse' => $a->inverse],
                array_filter($definition->actions(), fn (BulkAction $a): bool => PermissionExpression::parse($a->permission)->evaluate($user)),
            )),
            exportFormats: $this->transfer->canExport($definition, $user)
                ? array_map(fn (ExportFormat $format): string => $format->value, $definition->exportFormats())
                : [],
            defaultSort: $config->defaultSortValue(),
            archivable: is_subclass_of($definition->model, GenericEntity::class),
            importable: $this->transfer->canImport($definition, $user),
        );
    }

    /**
     * What the search box finds, in the words of the table ("Code, Patient, Treatment, Doctor"), for its placeholder.
     *
     * @return list<string>
     */
    private function searchHint(ListQueryConfig $config, SearchScope $scope): array
    {
        $columns = $config->columns();
        $labels = [];
        foreach ([...array_map(fn (string $c): string => Str::afterLast($c, '.'), $config->searchColumns()), ...$scope->fields] as $field) {
            $label = match (true) {
                isset($columns[$field]) => $columns[$field],
                // Arabic spellings are found too, but are not named again.
                str_ends_with($field, '_ar') => null,
                $field === 'code' => 'Code',
                in_array($field, ['first_name', 'last_name'], true) => $columns['full_name'] ?? 'Name',
                default => null,
            };
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * Columns with the enum behind them (from the model's casts, else its filter), so every list shows enum values
     * as coloured badges without a per-page cell template.
     *
     * @param  class-string<Model>  $model
     * @return list<array{field: string, label: string, enum?: string}>
     */
    private function columns(ListQueryConfig $config, string $model): array
    {
        $casts = (new $model)->getCasts();
        $filterEnums = [];
        foreach ($config->filters() as $filter) {
            if ($filter->enum !== null) {
                $filterEnums[$filter->field] = $filter->enum;
            }
        }

        $columns = [];
        foreach ($config->columns() as $field => $label) {
            $cast = $casts[$field] ?? null;
            $enum = is_string($cast) && enum_exists($cast) ? $cast : ($filterEnums[$field] ?? null);
            $columns[] = array_filter(
                ['field' => $field, 'label' => $label, 'enum' => $enum !== null ? class_basename($enum) : null],
                fn (?string $value): bool => $value !== null,
            );
        }

        return $columns;
    }
}
