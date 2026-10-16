<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Enums\SortDirection;

final readonly class SortField
{
    public function __construct(
        public string $field,
        public SortDirection $dir = SortDirection::Asc,
    ) {}

    /**
     * Parses the compact form: `-created_at` = desc, `created_at` = asc.
     */
    public static function fromCompact(string $value): self
    {
        return str_starts_with($value, '-')
            ? new self(substr($value, 1), SortDirection::Desc)
            : new self($value, SortDirection::Asc);
    }

    /**
     * @return array{field: string, dir: string}
     */
    public function toArray(): array
    {
        return ['field' => $this->field, 'dir' => $this->dir->value];
    }
}
