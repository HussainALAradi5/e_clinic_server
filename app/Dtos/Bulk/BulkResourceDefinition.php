<?php

declare(strict_types=1);

namespace App\Dtos\Bulk;

use App\Enums\Shared\ExportFormat;
use App\Interfaces\Transfer\ImportDefinition;
use App\Utilities\Query\ListQueryConfig;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A listable resource: its model, list whitelist, read permission, bulk actions, and whether it can be
 * exported (Excel / PDF / CSV) or imported (Excel) — each guarded by `<resource>.export` / `<resource>.import`.
 */
final class BulkResourceDefinition
{
    /** @var array<string, BulkAction> */
    private array $actions = [];

    /** @var list<ExportFormat> */
    private array $exportFormats = [];

    /** @var class-string<JsonResource>|null */
    private ?string $exportResource = null;

    /** @var (Closure(Builder<Model>): Builder<Model>)|null */
    private ?Closure $exportQuery = null;

    /** @var array<string, string> */
    private array $exportOnlyColumns = [];

    /** @var array<string, Closure(Model): string> */
    private array $exportValues = [];

    private ?string $permissionResource = null;

    /** @var class-string<ImportDefinition>|null */
    private ?string $importDefinition = null;

    private ?string $readExpression = null;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $model,
        public readonly ListQueryConfig $config,
    ) {}

    /**
     * @param  Closure(Model, ?string): mixed|array{class-string, string}  $handler  a closure or [AppointmentService::class, 'cancel']
     */
    public function action(
        string $name,
        string $permission,
        Closure|array $handler,
        bool $needsReason = false,
        bool $targetsDeleted = false,
        ?string $inverse = null,
    ): self {
        $run = $handler instanceof Closure
            ? $handler
            : fn (Model $model, ?string $reason): mixed => app($handler[0])->{$handler[1]}($model, ...($needsReason ? [$reason] : []));

        $this->actions[$name] = new BulkAction(
            name: $name,
            permission: $permission,
            handler: $run,
            needsReason: $needsReason,
            targetsDeleted: $targetsDeleted,
            inverse: $inverse,
        );

        return $this;
    }

    /**
     * Offer export. Rows are rendered with the list's own API resource, so the file shows what the table shows.
     *
     * @param  class-string<JsonResource>  $resource
     * @param  (Closure(Builder<Model>): Builder<Model>)|null  $query  eager loads / counts / extra conditions of the list
     * @param  list<ExportFormat>  $formats
     * @param  array<string, string>  $extraColumns  field => label, always added to exports (never shown in the table)
     * @param  array<string, Closure(Model): string>  $values  text of computed columns (e.g. audit "Record")
     */
    public function exportable(
        string $resource,
        ?Closure $query = null,
        array $formats = [ExportFormat::Xlsx, ExportFormat::Pdf, ExportFormat::Csv],
        array $extraColumns = [],
        array $values = [],
    ): self {
        $this->exportValues = $values;
        $this->exportResource = $resource;
        $this->exportQuery = $query;
        $this->exportFormats = $formats;
        $this->exportOnlyColumns = $extraColumns;

        return $this;
    }

    /**
     * Offer import from Excel.
     *
     * @param  class-string<ImportDefinition>  $definition
     */
    public function importable(string $definition): self
    {
        $this->importDefinition = $definition;

        return $this;
    }

    /** Permission prefix when it differs from the resource name (countries → `reference.export`). */
    public function permissionsOf(string $resource): self
    {
        $this->permissionResource = $resource;

        return $this;
    }

    /** @return array<string, Closure(Model): string> */
    public function exportValues(): array
    {
        return $this->exportValues;
    }

    /** @return array<string, string> */
    public function exportOnlyColumns(): array
    {
        return $this->exportOnlyColumns;
    }

    /** @return class-string<JsonResource>|null */
    public function exportResource(): ?string
    {
        return $this->exportResource;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyExportQuery(Builder $query): Builder
    {
        return $this->exportQuery !== null ? ($this->exportQuery)($query) : $query;
    }

    public function importDefinition(): ?ImportDefinition
    {
        return $this->importDefinition !== null ? app($this->importDefinition) : null;
    }

    public function permissionResource(): string
    {
        return $this->permissionResource ?? $this->resource;
    }

    public function exportPermission(): string
    {
        return $this->permissionResource().'.export';
    }

    public function importPermission(): string
    {
        return $this->permissionResource().'.import';
    }

    public function find(string $name): ?BulkAction
    {
        return $this->actions[$name] ?? null;
    }

    /** @return array<string, BulkAction> */
    public function actions(): array
    {
        return $this->actions;
    }

    /** @return list<ExportFormat> */
    public function exportFormats(): array
    {
        return $this->exportFormats;
    }

    /** Who may open the list (default `<resource>.read_all|<resource>.read_own`). */
    public function readableBy(string $expression): self
    {
        $this->readExpression = $expression;

        return $this;
    }

    public function readPermission(): string
    {
        return $this->readExpression ?? "{$this->resource}.read_all|{$this->resource}.read_own";
    }
}
