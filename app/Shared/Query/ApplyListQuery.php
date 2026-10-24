<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Enums\FilterFieldType;
use App\Shared\Enums\FilterOperator;
use App\Shared\Enums\LogicalOperator;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies a ListQuery to an Eloquent builder. Only whitelisted columns ever reach SQL and every value is bound.
 */
final class ApplyListQuery
{
    public function __construct(private readonly ClinicClock $clock) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, ListQuery $q): Builder
    {
        $this->applyVisibility($query, $q);
        $this->applySearch($query, $q);
        $this->applyDate($query, $q);

        if ($q->filter !== null) {
            $query->where(fn (Builder $nested) => $this->applyGroup($nested, $q->filter, $q->config));
        }

        $this->applySort($query, $q);

        if ($q->include !== []) {
            $query->with($q->include);
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>|CursorPaginator<int, TModel>
     */
    public function paginate(Builder $query, ListQuery $q): LengthAwarePaginator|CursorPaginator
    {
        $this->apply($query, $q);

        return $q->usesCursor()
            ? $query->cursorPaginate($q->perPage, ['*'], 'cursor', $q->cursor)
            : $query->paginate($q->perPage, ['*'], 'page', $q->page);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyVisibility(Builder $query, ListQuery $q): void
    {
        if (! GenericEntityScopes::applies($query)) {
            return;
        }

        if ($q->onlyDeleted) {
            GenericEntityScopes::onlyDeleted($query);
        } elseif (! $q->includeArchived) {
            GenericEntityScopes::notArchived($query);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySearch(Builder $query, ListQuery $q): void
    {
        if ($q->search === null) {
            return;
        }
        $scope = SearchScope::for($query->getModel(), $q->config);
        if ($scope->isEmpty()) {
            return;
        }

        $query->where(function (Builder $nested) use ($q, $scope): void {
            $pattern = '%'.self::escapeLike($q->search).'%';
            foreach ($scope->columns as $column) {
                $this->whereLike($nested, $column, $pattern, 'or');
            }
            foreach ($scope->relations as $relation => $columns) {
                $nested->orWhereHas($relation, function (Builder $related) use ($columns, $pattern): void {
                    $related->where(function (Builder $any) use ($columns, $pattern): void {
                        foreach ($columns as $column) {
                            $this->whereLike($any, $any->qualifyColumn($column), $pattern, 'or');
                        }
                    });
                });
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyDate(Builder $query, ListQuery $q): void
    {
        $date = $q->date;
        if ($date === null || ($date->from === null && $date->to === null)) {
            return;
        }

        $column = $q->config->columnFor($date->field);

        if (ListQueryConfig::isTimestampField($date->field)) {
            [$from, $to] = $this->clock->dayRangeUtc($date->from ?? $date->to, $date->to ?? $date->from);
            if ($date->from !== null) {
                $query->where($column, '>=', $from);
            }
            if ($date->to !== null) {
                $query->where($column, '<=', $to);
            }

            return;
        }

        if ($date->from !== null) {
            $query->whereDate($column, '>=', $date->from->toDateString());
        }
        if ($date->to !== null) {
            $query->whereDate($column, '<=', $date->to->toDateString());
        }
    }

    /**
     * and → where(fn), or → orWhere(fn) per child, not → whereNot(fn).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyGroup(Builder $query, FilterGroup $group, ListQueryConfig $config): void
    {
        if ($group->op === LogicalOperator::Not) {
            $query->whereNot(function (Builder $nested) use ($group, $config): void {
                foreach ($group->children as $child) {
                    $this->applyChild($nested, $child, $config, 'and');
                }
            });

            return;
        }

        $boolean = $group->op === LogicalOperator::Or ? 'or' : 'and';
        foreach ($group->children as $child) {
            $this->applyChild($query, $child, $config, $boolean);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyChild(Builder $query, FilterGroup|FilterCondition $child, ListQueryConfig $config, string $boolean): void
    {
        if ($child instanceof FilterGroup) {
            $query->where(fn (Builder $nested) => $this->applyGroup($nested, $child, $config), boolean: $boolean);

            return;
        }

        $query->where(fn (Builder $nested) => $this->applyCondition($nested, $child, $config), boolean: $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyCondition(Builder $query, FilterCondition $c, ListQueryConfig $config): void
    {
        // `id` is always filterable; qualify it so joined list queries stay unambiguous.
        $column = $c->field === 'id' ? $query->getModel()->getQualifiedKeyName() : $config->columnFor($c->field);
        $value = $c->value;

        if ($config->filter($c->field)?->type === FilterFieldType::Date && $this->applyDateCondition($query, $column, $c->op, $value)) {
            return;
        }

        match ($c->op) {
            FilterOperator::Eq => $query->where($column, '=', $value),
            FilterOperator::Ne => $query->where($column, '<>', $value),
            FilterOperator::Gt => $query->where($column, '>', $value),
            FilterOperator::Gte => $query->where($column, '>=', $value),
            FilterOperator::Lt => $query->where($column, '<', $value),
            FilterOperator::Lte => $query->where($column, '<=', $value),
            FilterOperator::In => $query->whereIn($column, $value),
            FilterOperator::Nin => $query->whereNotIn($column, $value),
            FilterOperator::Between => $query->whereBetween($column, $value),
            FilterOperator::Like => $this->whereLike($query, $column, '%'.self::escapeLike((string) $value).'%'),
            FilterOperator::Starts => $this->whereLike($query, $column, self::escapeLike((string) $value).'%'),
            FilterOperator::Null => $value ? $query->whereNull($column) : $query->whereNotNull($column),
        };
    }

    /**
     * Date filters compare the calendar day, so they also match where the database keeps a time with the date
     * (SQLite stores `2026-10-25 00:00:00`). Returns false for operators that need no special handling.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyDateCondition(Builder $query, string $column, FilterOperator $op, mixed $value): bool
    {
        $comparison = match ($op) {
            FilterOperator::Eq => '=',
            FilterOperator::Ne => '<>',
            FilterOperator::Gt => '>',
            FilterOperator::Gte => '>=',
            FilterOperator::Lt => '<',
            FilterOperator::Lte => '<=',
            default => null,
        };
        if ($comparison !== null) {
            $query->whereDate($column, $comparison, $value);

            return true;
        }
        if ($op === FilterOperator::Between && is_array($value)) {
            $query->whereDate($column, '>=', $value[0])->whereDate($column, '<=', $value[1]);

            return true;
        }

        return false;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySort(Builder $query, ListQuery $q): void
    {
        $hasId = false;
        foreach ($q->sort as $sort) {
            $query->orderBy($q->config->columnFor($sort->field), $sort->dir->value);
            $hasId = $hasId || $sort->field === 'id';
        }

        if (! $hasId) {
            $direction = $q->sort[0]->dir->value ?? 'asc';
            $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
        }
    }

    /**
     * Case-insensitive, portable LIKE with an explicit escape character.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function whereLike(Builder $query, string $column, string $pattern, string $boolean = 'and'): void
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        $query->whereRaw("LOWER({$wrapped}) LIKE ? ESCAPE '!'", [mb_strtolower($pattern)], $boolean);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
