<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Query\ListQueryConfig;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model-side helper: `Treatment::query()->listQuery($q)` and the model's default whitelist.
 */
trait HasListQuery
{
    abstract public static function listQueryConfig(): ListQueryConfig;

    /**
     * @param  Builder<static>  $builder
     * @return Builder<static>
     */
    public function scopeListQuery(Builder $builder, ListQuery $query): Builder
    {
        return app(ApplyListQuery::class)->apply($builder, $query);
    }
}
