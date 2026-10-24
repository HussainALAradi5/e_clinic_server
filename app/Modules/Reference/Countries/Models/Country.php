<?php

declare(strict_types=1);

namespace App\Modules\Reference\Countries\Models;

use App\Shared\Contracts\Auditable;
use App\Shared\Query\FilterOptions;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\HasListQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ISO-3166 country, keyed by its alpha-2 code. Never deleted, only deactivated.
 *
 * @property string $code
 * @property string $iso3
 * @property string $name
 * @property string $name_ar
 * @property string $nationality
 * @property ?string $nationality_ar
 * @property string $phone_code
 * @property ?string $currency_code
 * @property bool $is_active
 * @property int $sort_order
 */
class Country extends Model implements Auditable
{
    use AuditsChanges;
    use HasListQuery;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('countries')
            ->sortable('sort_order', 'code', 'name', 'nationality', 'phone_code')
            ->filterable('is_active', ['eq'], type: 'boolean')
            ->filterable('currency_code', ['eq', 'in'], options: FilterOptions::inUse(self::class, 'currency_code'))
            ->filterable('code', ['eq', 'in'])
            ->searchIn('iso3')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('nationality', 'Nationality')
            ->column('phone_code', 'Dial code')
            ->column('currency_code', 'Currency')
            ->column('is_active', 'Active')
            ->defaultSort('sort_order,name');
    }

    /**
     * Labeller for country-code filters (`BH` → Bahrain, or → Bahraini with `nationality`).
     *
     * @return Closure(list<string>): array<string, string>
     */
    public static function labels(string $column = 'name'): Closure
    {
        return static fn (array $codes): array => self::query()->whereIn('code', $codes)->pluck($column, 'code')->all();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
