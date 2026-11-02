<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Throwable;

/**
 * What the search box of a list looks in — worked out from the list's own columns, like pagination, so no list
 * forgets one: every text column shown in the table, and every column that shows a related record (patient, doctor,
 * room …) through that record's own search (its name, code, phone …). `searchIn` / `searchInRelation` add text that is
 * not a column (a phone number, a reference). Enum, number, date and id columns are left to the filters.
 *
 * Built once per model and list.
 */
final class SearchScope
{
    /** Text columns of a related record without a list of its own (a country, a specialty …). */
    private const LABEL_COLUMNS = ['code', 'name', 'name_ar', 'title', 'display_name', 'first_name', 'last_name'];

    /** @var array<string, self> */
    private static array $built = [];

    /** @var array<string, list<string>> table => its text columns */
    private static array $textColumns = [];

    /**
     * @param  list<string>  $columns  the list's own columns (qualified when the list joins tables)
     * @param  array<string, list<string>>  $relations  relation path => its columns
     * @param  list<string>  $fields  table columns (fields) the search covers, for the search box hint
     */
    private function __construct(
        public readonly array $columns,
        public readonly array $relations,
        public readonly array $fields,
    ) {}

    /**
     * @param  bool  $withRelated  false when the record is itself shown in another list (its own text only, no loops)
     */
    public static function for(Model $model, ListQueryConfig $config, bool $withRelated = true): self
    {
        $key = implode('|', [$model::class, implode(',', array_keys($config->columns())), implode(',', $config->searchColumns()), implode(',', array_keys($config->searchRelations())), (int) $withRelated]);

        return self::$built[$key] ??= self::build($model, $config, $withRelated);
    }

    /** Forget what was worked out (tests that change tables). */
    public static function flush(): void
    {
        self::$built = [];
        self::$textColumns = [];
    }

    public function isEmpty(): bool
    {
        return $this->columns === [] && $this->relations === [];
    }

    private static function build(Model $model, ListQueryConfig $config, bool $withRelated): self
    {
        $columns = $config->searchColumns();
        $relations = $config->searchRelations();
        $fields = [];
        $text = self::textColumns($model->getTable());
        $casts = $model->getCasts();
        $filters = $config->filters();

        foreach (array_keys($config->columns()) as $field) {
            if (in_array($field, $text, true)) {
                // Enum values (status, type …) are picked with filters, not typed.
                if (! self::isEnum($casts[$field] ?? null) && ($filters[$field]->enum ?? null) === null) {
                    $columns[] = $model->qualifyColumn($field);
                    $fields[] = $field;
                }

                continue;
            }
            $related = $withRelated ? self::related($model, $field) : null;
            if ($related !== null) {
                foreach (self::identify($related) as $path => $relatedColumns) {
                    $key = $path === '' ? Str::camel($field) : Str::camel($field).'.'.$path;
                    $relations[$key] = array_values(array_unique([...$relations[$key] ?? [], ...$relatedColumns]));
                }
                $fields[] = $field;
            }
        }

        // An explicit column also counts when written plain ('code') or qualified ('treatments.code'),
        // and the Arabic spelling of a column is searched with it (name → name_ar).
        $qualified = [];
        foreach ($columns as $column) {
            $column = str_contains($column, '.') ? $column : $model->qualifyColumn($column);
            $qualified[] = $column;
            [$table, $name] = explode('.', $column, 2);
            if ($table === $model->getTable() && in_array($name.'_ar', $text, true)) {
                $qualified[] = $model->qualifyColumn($name.'_ar');
            }
        }
        $columns = array_values(array_unique($qualified));

        return new self($columns, $relations, array_values(array_unique($fields)));
    }

    /**
     * How a related record is found: what its own list's search finds (its text columns and extras, with
     * `persons.first_name` style columns turned into the relation that reaches that table), else its name / code.
     *
     * @return array<string, list<string>> relation path below the record ('' = the record itself) => columns
     */
    private static function identify(Model $related): array
    {
        $config = method_exists($related, 'listQueryConfig') ? (new ReflectionMethod($related, 'listQueryConfig'))->invoke(null) : null;
        if (! $config instanceof ListQueryConfig) {
            $labels = array_values(array_intersect(self::LABEL_COLUMNS, self::textColumns($related->getTable())));

            return $labels === [] ? [] : ['' => $labels];
        }

        $own = self::for($related, $config, withRelated: false);
        $paths = [];
        foreach ($own->columns as $column) {
            [$table, $name] = explode('.', $column, 2);
            $path = $table === $related->getTable() ? '' : self::relationToTable($related, $table);
            if ($path !== null) {
                $paths[$path][] = $name;
            }
        }
        foreach ($own->relations as $path => $columns) {
            $paths[$path] = [...$paths[$path] ?? [], ...$columns];
        }

        return $paths;
    }

    /** The relation of a model that reaches a joined table (`persons` → `person`), if any. */
    private static function relationToTable(Model $model, string $table): ?string
    {
        foreach ([Str::camel(Str::singular($table)), Str::camel($table)] as $name) {
            $related = self::related($model, $name);
            if ($related !== null && $related->getTable() === $table) {
                return $name;
            }
        }

        return null;
    }

    /** The model a column's relation points to (`patient` → Patient), unless it is polymorphic. */
    private static function related(Model $model, string $field): ?Model
    {
        $method = Str::camel($field);
        if (! method_exists($model, $method) || (new ReflectionMethod($model, $method))->getNumberOfRequiredParameters() > 0) {
            return null;
        }
        try {
            $relation = $model->{$method}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation && ! $relation instanceof MorphTo ? $relation->getRelated() : null;
    }

    /**
     * Text columns of a table, without ids.
     *
     * @return list<string>
     */
    private static function textColumns(string $table): array
    {
        return self::$textColumns[$table] ??= array_values(array_map(
            fn (array $column): string => $column['name'],
            array_filter(Schema::getColumns($table), fn (array $column): bool => preg_match('/char|text|string|clob/i', (string) $column['type_name']) === 1
                && $column['name'] !== 'id' && ! str_ends_with($column['name'], '_id') && ! str_ends_with($column['name'], '_type')),
        ));
    }

    private static function isEnum(mixed $cast): bool
    {
        return is_string($cast) && enum_exists($cast);
    }
}
