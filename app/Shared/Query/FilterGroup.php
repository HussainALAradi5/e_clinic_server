<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Enums\LogicalOperator;

final readonly class FilterGroup
{
    /**
     * @param  list<FilterGroup|FilterCondition>  $children
     */
    public function __construct(
        public LogicalOperator $op,
        public array $children,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [$this->op->value => array_map(fn (FilterGroup|FilterCondition $child): array => $child->toArray(), $this->children)];
    }
}
