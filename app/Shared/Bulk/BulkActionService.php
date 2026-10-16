<?php

declare(strict_types=1);

namespace App\Shared\Bulk;

use App\Shared\Access\PermissionExpression;
use App\Shared\Contracts\RendersApiError;
use App\Shared\Contracts\TaskDispatcher;
use App\Shared\Data\BulkActionData;
use App\Shared\Exceptions\BulkActionNotAllowedException;
use App\Shared\Exceptions\UnknownResourceException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs one action on many records of any resource. Each record goes through the normal service method
 * inside its own transaction (savepoint), so partial success is reported instead of failing the batch.
 * Dry runs execute the same path and roll every record back.
 */
class BulkActionService
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly ApplyListQuery $listQuery,
    ) {}

    public function run(string $resource, BulkActionData $data, Authenticatable $user): BulkResult
    {
        $definition = $this->registry->for($resource);

        if (! PermissionExpression::parse($definition->readPermission())->evaluate($user)) {
            throw new UnknownResourceException;
        }

        $action = $definition->find($data->action) ?? throw new BulkActionNotAllowedException;

        if (! PermissionExpression::parse($action->permission)->evaluate($user)) {
            throw new BulkActionNotAllowedException;
        }

        if ($action->needsReason && blank($data->reason)) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => 'reason'])]);
        }

        $ids = $this->targetIds($definition, $action, $data);
        $queued = ! $data->dryRun && count($ids) > Config::integer('clinic.bulk.sync_limit');
        $result = new BulkResult($action->name, count($ids), $data->dryRun, $queued, $action->inverse);

        if ($queued) {
            $task = app(TaskDispatcher::class)->start(BulkActionTaskHandler::TYPE, [
                'resource' => $resource,
                'action' => $action->name,
                'ids' => $ids,
                'reason' => $data->reason,
            ], $user);
            $result->taskId = (string) $task->getKey();

            return $result;
        }

        return $this->process($definition, $action, $ids, $data->reason, $user, $result);
    }

    /**
     * @param  list<string>  $ids
     * @param  (callable(int $done): bool)|null  $onChunk  progress callback between chunks; returning false stops (task cancelled)
     */
    public function process(BulkResourceDefinition $definition, BulkAction $action, array $ids, ?string $reason, Authenticatable $user, BulkResult $result, ?callable $onChunk = null): BulkResult
    {
        $permission = PermissionExpression::parse($action->permission);
        $done = 0;

        foreach (array_chunk($ids, 100) as $chunk) {
            if ($onChunk !== null && ! $onChunk($done)) {
                break;
            }
            $done += count($chunk);

            $records = $this->baseQuery($definition, $action)->whereKey($chunk)->get()->keyBy(fn (Model $m): string => (string) $m->getKey());

            foreach ($chunk as $id) {
                $record = $records->get($id);

                try {
                    if ($record === null) {
                        throw new ModelNotFoundException;
                    }
                    if (! $permission->evaluate($user, $record)) {
                        throw new AuthorizationException;
                    }

                    $this->runOne($action, $record, $reason, $result->dryRun);
                    $result->succeed($id);
                } catch (Throwable $e) {
                    [$code, $message] = $this->describe($e);
                    $result->fail($id, $code, $message);
                }
            }
        }

        return $result;
    }

    private function runOne(BulkAction $action, Model $record, ?string $reason, bool $dryRun): void
    {
        DB::beginTransaction();

        try {
            ($action->handler)($record, $reason);
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Explicit ids, or the ids matching the ListQuery filter ("select all matching").
     *
     * @return list<string>
     */
    private function targetIds(BulkResourceDefinition $definition, BulkAction $action, BulkActionData $data): array
    {
        if ($data->ids !== null) {
            return $data->ids;
        }

        $query = ListQuery::fromArray($data->filter ?? [], $definition->config);
        $builder = $this->listQuery->apply($this->baseQuery($definition, $action), $query);

        $ids = $builder->limit(Config::integer('clinic.bulk.max_matching'))->pluck($builder->getModel()->getQualifiedKeyName())->all();

        return array_values(array_map(strval(...), $ids));
    }

    /**
     * @return Builder<Model>
     */
    private function baseQuery(BulkResourceDefinition $definition, BulkAction $action): Builder
    {
        $query = $definition->model::query();

        return $action->targetsDeleted && GenericEntityScopes::applies($query) ? GenericEntityScopes::onlyDeleted($query) : $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof RendersApiError => [$e->errorCode(), $e->getMessage()],
            $e instanceof ModelNotFoundException => ['NOT_FOUND', __('errors.NOT_FOUND')],
            $e instanceof AuthorizationException => ['FORBIDDEN', __('errors.FORBIDDEN')],
            $e instanceof ValidationException => ['VALIDATION_FAILED', $e->getMessage()],
            default => $this->unexpected($e),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function unexpected(Throwable $e): array
    {
        report($e);

        return ['SERVER_ERROR', __('errors.SERVER_ERROR')];
    }
}
