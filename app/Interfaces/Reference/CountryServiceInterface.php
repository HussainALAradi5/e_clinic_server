<?php

declare(strict_types=1);

namespace App\Interfaces\Reference;

use App\Dtos\Reference\CountryData;
use App\Models\Reference\Country;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CountryServiceInterface
{
    /**
     * Admin list (generic list query; inactive countries only on request).
     *
     * @return LengthAwarePaginator<int, Country>|CursorPaginator<int, Country>
     */
    public function list(ListQuery $query, bool $includeInactive = false): LengthAwarePaginator|CursorPaginator;

    /**
     * Every active country for pickers — cached forever, flushed on change.
     *
     * @return Collection<int, Country>
     */
    public function options(): Collection;

    public function find(string $code): Country;

    /** True when the code is an active country (validation of nationality / address / company country). */
    public function isActive(string $code): bool;

    /** Names, nationality labels, order, active flag (deactivating keeps existing data). */
    public function update(Country $country, CountryData $data): Country;
}
