<?php

declare(strict_types=1);

namespace App\Modules\Reference\Countries\Services;

use App\Modules\Reference\Countries\Contracts\CountryServiceInterface;
use App\Modules\Reference\Countries\Data\CountryData;
use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class CountryService implements CountryServiceInterface
{
    public const CACHE_KEY = 'reference:countries:active';

    public function __construct(private readonly ApplyListQuery $listQuery) {}

    public function list(ListQuery $query, bool $includeInactive = false): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate(Country::query()->when(! $includeInactive, fn ($q) => $q->active()), $query);
    }

    public function options(): Collection
    {
        /** @var Collection<int, Country> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): Collection => Country::query()->active()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function find(string $code): Country
    {
        return Country::query()->findOrFail(strtoupper($code));
    }

    public function isActive(string $code): bool
    {
        return $this->options()->contains('code', strtoupper($code));
    }

    public function update(Country $country, CountryData $data): Country
    {
        $country->fill($data->attributes())->save();
        Cache::forget(self::CACHE_KEY);

        return $country;
    }
}
