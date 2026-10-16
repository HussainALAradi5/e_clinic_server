<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Shared\Contracts\GenericEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Archive / soft-delete query conditions shared by the HasGenericEntity scopes and generic code
 * (list queries, bulk actions, base service) that only knows it holds a GenericEntity builder.
 */
final class GenericEntityScopes
{
    public const NOT_DELETED = 'not_deleted';

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function notArchived(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_archived'), false);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withDeleted(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::NOT_DELETED);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function onlyDeleted(Builder $query): Builder
    {
        return self::withDeleted($query)->where($query->qualifyColumn('is_deleted'), true);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function applies(Builder $query): bool
    {
        return $query->getModel() instanceof GenericEntity;
    }
}
