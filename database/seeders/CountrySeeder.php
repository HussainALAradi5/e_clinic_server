<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Reference\Country;
use App\Services\Reference\CountryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * ISO-3166 countries with Arabic names, nationality labels, dial and currency codes (GCC first).
 * Source: database/seeders/data/countries.json (from the mledoze/countries dataset, ODbL).
 * Re-running keeps admin edits to labels / order / active flag and only adds missing countries.
 */
final class CountrySeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string) file_get_contents(__DIR__.'/data/countries.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach (array_chunk($rows, 100) as $chunk) {
            Country::query()->insertOrIgnore(array_map(fn (array $row): array => [...$row, 'is_active' => true], $chunk));
        }

        Cache::forget(CountryService::CACHE_KEY);
    }
}
