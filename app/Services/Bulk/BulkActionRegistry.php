<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use App\Dtos\Bulk\BulkResourceDefinition;
use App\Exceptions\Shared\UnknownResourceException;
use App\Interfaces\Shared\GenericEntity;
use App\Services\Base\BaseEntityService;
use App\Utilities\Query\ListQueryConfig;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry of listable resources and their bulk actions, filled by each module's ServiceProvider.
 * archive / unarchive / delete / restore are added automatically for GenericEntity resources.
 */
final class BulkActionRegistry
{
    /** @var array<string, BulkResourceDefinition> */
    private array $resources = [];

    /**
     * @param  class-string<Model>  $model
     * @param  class-string<BaseEntityService<covariant Model&GenericEntity>>|null  $service  runs the generic actions (keeps its delete guards)
     * @param  bool  $genericActions  false = list schema only (no archive / delete / restore bulk actions)
     */
    public function register(string $resource, string $model, ListQueryConfig $config, ?string $service = null, bool $genericActions = true): BulkResourceDefinition
    {
        $definition = new BulkResourceDefinition($resource, $model, $config->resource($resource));

        if ($genericActions && is_subclass_of($model, GenericEntity::class)) {
            $run = fn (string $method): Closure => fn (Model $record): mixed => $service !== null
                ? app($service)->{$method}($record)
                : $record->{$method === 'delete' ? 'softDelete' : $method}();

            $definition
                ->action('archive', "{$resource}.write", $run('archive'), inverse: 'unarchive')
                ->action('unarchive', "{$resource}.write", $run('unarchive'), inverse: 'archive')
                ->action('delete', "{$resource}.delete", $run('delete'), inverse: 'restore')
                ->action('restore', "{$resource}.delete", $run('restore'), targetsDeleted: true, inverse: 'delete');
        }

        return $this->resources[$resource] = $definition;
    }

    public function has(string $resource): bool
    {
        return isset($this->resources[$resource]);
    }

    public function for(string $resource): BulkResourceDefinition
    {
        return $this->resources[$resource] ?? throw new UnknownResourceException;
    }

    /** @return array<string, BulkResourceDefinition> */
    public function all(): array
    {
        return $this->resources;
    }
}
