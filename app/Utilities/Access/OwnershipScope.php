<?php

declare(strict_types=1);

namespace App\Utilities\Access;

use App\Interfaces\Shared\OwnedByUser;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Record rules for lists: a user with `<resource>.read_own` but not `<resource>.read_all`
 * only sees the rows the model's OwnedByUser::scopeOwnedBy() allows (others are 404 — IDOR protection).
 */
final class OwnershipScope
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $resource, ?Authenticatable $user): Builder
    {
        if ($user instanceof Authorizable && $user->can("{$resource}.read_all")) {
            return $query;
        }

        $model = $query->getModel();

        if ($user === null || ! $model instanceof OwnedByUser) {
            return $query->whereRaw('1 = 0');
        }

        return $model->scopeOwnedBy($query, $user);
    }
}
