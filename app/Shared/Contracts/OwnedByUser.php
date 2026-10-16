<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a user may only see when they own them (patient portal, doctor, employee self-service).
 */
interface OwnedByUser
{
    public function isOwnedBy(Authenticatable $user): bool;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder;
}
