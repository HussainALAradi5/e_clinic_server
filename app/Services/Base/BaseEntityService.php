<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Interfaces\Shared\GenericEntity;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base for module services: list (ListQuery), find, archive, unarchive, soft delete and restore,
 * each state change wrapped in a transaction. Subclasses add business guards via beforeDelete().
 *
 * @template TModel of Model&GenericEntity
 */
abstract class BaseEntityService
{
    public function __construct(protected readonly ApplyListQuery $listQuery) {}

    /**
     * Base query (eager loads, ownership scope …) for lists and lookups.
     *
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    /**
     * @return LengthAwarePaginator<int, TModel>|CursorPaginator<int, TModel>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    /**
     * @return TModel
     */
    public function find(string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * Includes soft-deleted rows (restore screens).
     *
     * @return TModel
     */
    public function findWithDeleted(string $id): Model
    {
        return GenericEntityScopes::withDeleted($this->query())->findOrFail($id);
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    public function archive(Model $model): Model
    {
        return DB::transaction(fn (): Model => $model->archive());
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    public function unarchive(Model $model): Model
    {
        return DB::transaction(fn (): Model => $model->unarchive());
    }

    /**
     * Soft delete.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->beforeDelete($model);
            $model->softDelete();
        });
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    public function restore(Model $model): Model
    {
        return DB::transaction(fn (): Model => $model->restore());
    }

    /**
     * Business guards before a soft delete (throw a ConflictException to block it).
     *
     * @param  TModel  $model
     */
    protected function beforeDelete(Model $model): void {}
}
