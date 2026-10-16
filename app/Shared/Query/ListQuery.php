<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Support\ClinicClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Immutable list query (API conventions §5): pagination, sort, date filter, field filters with AND/OR/NOT groups,
 * search and archive / deleted flags — validated against the endpoint's ListQueryConfig (422 on anything else).
 */
final readonly class ListQuery
{
    public const MAX_DEPTH = 3;

    public const MAX_CONDITIONS = 20;

    public const MAX_SEARCH_LENGTH = 100;

    /**
     * @param  list<SortField>  $sort
     * @param  list<string>  $include
     */
    public function __construct(
        public ListQueryConfig $config,
        public int $page = 1,
        public int $perPage = 15,
        public ?string $cursor = null,
        public array $sort = [],
        public ?DateFilter $date = null,
        public ?FilterGroup $filter = null,
        public ?string $search = null,
        public bool $includeArchived = false,
        public bool $onlyDeleted = false,
        public array $include = [],
    ) {}

    public static function fromRequest(Request $request, ListQueryConfig $config): self
    {
        $query = self::fromArray($request->query(), $config);

        if ($query->onlyDeleted) {
            $resource = $config->resourceName();
            if ($resource === null || ! $request->user()?->can($resource.'.delete')) {
                throw new AuthorizationException;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $params  snake_case query parameters (also used for bulk "select all matching")
     *
     * @throws ValidationException
     */
    public static function fromArray(array $params, ListQueryConfig $config): self
    {
        $parser = new ListQueryParser($config, app(ClinicClock::class));
        $query = $parser->parse($params);

        if ($parser->errors !== []) {
            throw ValidationException::withMessages($parser->errors);
        }

        return $query;
    }

    public function usesCursor(): bool
    {
        return $this->cursor !== null || $this->config->usesCursor();
    }

    /**
     * Echoed in the list response `meta`.
     *
     * @return array{sort: list<array{field: string, dir: string}>, filters: array<string, mixed>|null, date: array<string, mixed>|null}
     */
    public function toMeta(): array
    {
        return [
            'sort' => array_map(fn (SortField $s): array => $s->toArray(), $this->sort),
            'filters' => $this->filter?->toArray(),
            'date' => $this->date?->toArray(),
        ];
    }
}
