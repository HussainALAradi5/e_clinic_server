<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Data;

/**
 * One row of a role's access matrix: the five CRUD flags of a resource plus its granted special actions.
 */
final readonly class AccessRowData
{
    /**
     * @param  list<string>  $actions  granted special actions (without the resource prefix)
     * @param  list<string>  $availableActions
     */
    public function __construct(
        public string $resource,
        public string $module = '',
        public bool $readOwn = false,
        public bool $readAll = false,
        public bool $create = false,
        public bool $write = false,
        public bool $delete = false,
        public array $actions = [],
        public array $availableActions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row  validated snake_case row
     */
    public static function fromArray(array $row): self
    {
        $flag = fn (string $key): bool => filter_var($row[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            resource: (string) $row['resource'],
            readOwn: $flag('read_own') || $flag('read_all'),
            readAll: $flag('read_all'),
            create: $flag('create'),
            write: $flag('write'),
            delete: $flag('delete'),
            actions: array_values(array_unique(array_map('strval', (array) ($row['actions'] ?? [])))),
        );
    }

    /**
     * Permission names this row grants (`read_all` implies `read_own`).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $flags = array_keys(array_filter([
            'read_own' => $this->readOwn || $this->readAll,
            'read_all' => $this->readAll,
            'create' => $this->create,
            'write' => $this->write,
            'delete' => $this->delete,
        ]));

        return array_map(fn (string $ability): string => "{$this->resource}.{$ability}", [...$flags, ...$this->actions]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'module' => $this->module,
            'read_own' => $this->readOwn || $this->readAll,
            'read_all' => $this->readAll,
            'create' => $this->create,
            'write' => $this->write,
            'delete' => $this->delete,
            'actions' => $this->actions,
            'available_actions' => $this->availableActions,
        ];
    }
}
