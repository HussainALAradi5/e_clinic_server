<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Models;

use App\Enums\Reference\CompanyType;
use App\Modules\Reference\Addresses\Models\Address;
use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * External organisation: medicine manufacturer, supplier, insurer, external lab, referral hospital …
 *
 * @property string $id
 * @property string $code
 * @property CompanyType $type
 * @property string $name
 * @property ?string $name_ar
 * @property ?string $description
 * @property string $country_code
 * @property ?string $address_id
 * @property ?string $phone
 * @property ?string $email
 * @property ?string $website
 * @property ?string $contact_person
 * @property ?string $vat_number
 * @property ?string $cr_number
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Country $country
 * @property-read ?Address $address
 */
class Company extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'CMP';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('companies')
            ->sortable('code', 'name', 'type', 'country_code', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: CompanyType::class)
            ->filterable('country_code', ['eq', 'in'])
            ->filterable('code', ['eq', 'in', 'starts'])
            ->searchIn('code', 'name', 'name_ar', 'contact_person', 'email', 'phone', 'vat_number', 'cr_number')
            ->includes('country', 'address.country')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('type', 'Type')
            ->column('country_code', 'Country')
            ->column('contact_person', 'Contact')
            ->column('phone', 'Phone')
            ->column('email', 'Email')
            ->dateFields('created_at')
            ->defaultSort('name');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
