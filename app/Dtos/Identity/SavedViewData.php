<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

/**
 * Saved view input; on update only keys in `$provided` are applied.
 */
final readonly class SavedViewData
{
    /**
     * @param  array<string, mixed>|null  $query
     * @param  list<mixed>|null  $columns
     * @param  list<string>  $provided
     */
    public function __construct(
        public ?string $resource = null,
        public ?string $name = null,
        public ?array $query = null,
        public ?array $columns = null,
        public ?bool $isShared = null,
        public ?bool $isDefault = null,
        public array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            resource: isset($input['resource']) ? (string) $input['resource'] : null,
            name: isset($input['name']) ? (string) $input['name'] : null,
            query: isset($input['query']) ? (array) $input['query'] : null,
            columns: isset($input['columns']) ? array_values((array) $input['columns']) : null,
            isShared: isset($input['is_shared']) ? (bool) $input['is_shared'] : null,
            isDefault: isset($input['is_default']) ? (bool) $input['is_default'] : null,
            provided: array_keys($input),
        );
    }
}
