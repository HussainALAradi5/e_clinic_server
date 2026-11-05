<?php

declare(strict_types=1);

namespace App\Dtos\Communication;

/**
 * Finding a colleague to message: free text (name, phone, email or staff no.) and the page wanted.
 */
final readonly class ContactSearch
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public function __construct(
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * Words of the search (each must match), empty when there is none.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $this->search)) ?: [], fn (string $w): bool => $w !== ''));
    }
}
