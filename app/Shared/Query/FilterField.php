<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Enums\FilterFieldType;
use App\Shared\Enums\FilterOperator;

/**
 * One whitelisted filter: allowed operators, the real column and how the client should edit it.
 */
final readonly class FilterField
{
    /**
     * @param  list<FilterOperator>  $operators
     * @param  class-string<\BackedEnum>|null  $enum
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
    ) {}

    public function allows(FilterOperator $op): bool
    {
        return in_array($op, $this->operators, true);
    }
}
