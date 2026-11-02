<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use App\Enums\Shared\FilterFieldType;
use App\Enums\Shared\FilterOperator;
use Closure;

/**
 * One whitelisted filter: allowed operators, the real column and how the client should edit it.
 */
final readonly class FilterField
{
    /**
     * @param  list<FilterOperator>  $operators
     * @param  class-string<\BackedEnum>|null  $enum
     * @param  (Closure(): array<string, string>)|null  $options  value => label, for Choice filters
     */
    public function __construct(
        public string $field,
        public array $operators,
        public string $column,
        public FilterFieldType $type = FilterFieldType::Text,
        public ?string $enum = null,
        public ?string $resource = null,
        public ?string $label = null,
        public bool $hidden = false,
        public ?Closure $options = null,
    ) {}

    public function allows(FilterOperator $op): bool
    {
        return in_array($op, $this->operators, true);
    }
}
