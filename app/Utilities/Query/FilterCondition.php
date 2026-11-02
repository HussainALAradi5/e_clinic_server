<?php

declare(strict_types=1);

namespace App\Utilities\Query;

use App\Enums\Shared\FilterOperator;

final readonly class FilterCondition
{
    public function __construct(
        public string $field,
        public FilterOperator $op,
        public mixed $value,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return [$this->field => [$this->op->value => $this->value]];
    }
}
