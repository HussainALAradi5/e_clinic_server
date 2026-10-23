<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Access\PermissionExpression;
use App\Shared\Bulk\BulkAction;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Contracts\GenericEntity;
use App\Shared\Enums\ExportFormat;
use App\Shared\Enums\FilterOperator;
use App\Shared\Exceptions\UnknownResourceException;
use App\Shared\Transfer\TransferGuard;
use Illuminate\Contracts\Auth\Authenticatable;

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
                // Accepted from links (e.g. the orders of one visit) but not offered in the filter panel.
                'hidden' => $f->hidden ?: null,
            ], fn (mixed $value): bool => $value !== null), $config->filters())),
            sortable: $config->sortableFields(),
            search: $config->searchColumns() !== [],
            dateFields: $config->dateFieldNames(),
            columns: array_map(fn (string $field, string $label): array => ['field' => $field, 'label' => $label], array_keys($config->columns()), $config->columns()),
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
}
