<?php

declare(strict_types=1);

namespace App\Shared\Bulk;

use App\Shared\Enums\ExportFormat;
use App\Shared\Query\ListQueryConfig;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A listable resource: its model, list whitelist, read permission, bulk actions and export formats.
 */
final class BulkResourceDefinition
{
    /** @var array<string, BulkAction> */
    private array $actions = [];

    /** @var list<ExportFormat> */
    private array $exportFormats = [];

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

    public function exports(ExportFormat|string ...$formats): self
    {
        $this->exportFormats = array_map(fn (ExportFormat|string $f): ExportFormat => $f instanceof ExportFormat ? $f : ExportFormat::from($f), array_values($formats));

        return $this;
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
