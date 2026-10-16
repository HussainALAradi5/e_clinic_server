<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Models;

use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Contracts\Auditable;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\Blameable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Postal address value object, owned by exactly one record through its `address_id` (never shared).
 * Bahrain format: flat / building / road / block / area; international: line1 / line2 / city / postal code.
 *
 * @property string $id
 * @property string $country_code
 * @property ?string $flat
 * @property ?string $building
 * @property ?string $road
 * @property ?string $block
 * @property ?string $area
 * @property ?string $city
 * @property ?string $governorate
 * @property ?string $postal_code
 * @property ?string $line1
 * @property ?string $line2
 * @property ?string $latitude
 * @property ?string $longitude
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Country $country
 */
class Address extends Model implements Auditable
{
    use AuditsChanges;
    use Blameable;
    use HasUuids;

    public const FIELDS = ['country_code', 'flat', 'building', 'road', 'block', 'area', 'city', 'governorate', 'postal_code', 'line1', 'line2', 'latitude', 'longitude'];

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
