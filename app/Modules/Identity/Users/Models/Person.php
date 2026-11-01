<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Models;

use App\Enums\Identity\UserType;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Reference\Addresses\Models\Address;
use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Access\OwnershipScope;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\Gender;
use App\Shared\Models\BaseModel;
use App\Shared\Query\FilterOptions;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Support\BlindIndex;
use App\Shared\Support\PhoneNumber;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\PersonFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Root record of every human (patients and staff). Subtypes (users, patients, employees) share its id.
 *
 * @property string $id
 * @property string $code
 * @property string $first_name
 * @property string $last_name
 * @property ?string $first_name_ar
 * @property ?string $last_name_ar
 * @property Gender $gender
 * @property CarbonImmutable $date_of_birth
 * @property ?string $national_id
 * @property ?string $national_id_hash
 * @property ?string $nationality_code
 * @property ?string $photo_path
 * @property string $phone
 * @property ?string $email
 * @property ?string $address_id
 * @property string $language
 * @property list<string> $notification_opt_out
 * @property int $version
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read string $full_name
 * @property-read ?User $user
 * @property-read ?Address $address
 * @property-read ?Country $nationality
 */
#[UseFactory(PersonFactory::class)]
class Person extends BaseModel implements OwnedByUser
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    use HasListQuery;
    use HasVersion;

    public const BLIND_INDEX_CONTEXT = 'national_id';

    protected $table = 'persons';

    protected $hidden = ['national_id_hash'];

    protected $attributes = ['language' => 'en', 'notification_opt_out' => '[]'];

    public static function codePrefix(): string
    {
        return 'PRS';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('people')
            ->sortable('code', 'first_name', 'last_name', 'date_of_birth', 'created_at')
            ->filterable('gender', enum: Gender::class)
            ->filterable('date_of_birth', ['eq', 'gte', 'lte', 'between'], type: 'date')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->filterable('nationality_code', label: 'Nationality', options: FilterOptions::inUse(self::class, 'nationality_code', Country::labels('nationality')))
            ->filterable('language', options: FilterOptions::languages(self::class))
            ->searchIn('first_name', 'last_name')
            ->column('code', 'Code')
            ->column('full_name', 'Name')
            ->column('gender', 'Gender')
            ->column('date_of_birth', 'Date of birth')
            ->column('phone', 'Mobile')
            ->column('email', 'Email')
            ->column('account', 'Account')
            ->dateFields('created_at', 'date_of_birth')
            ->defaultSort('last_name,first_name');
    }

    protected static function booted(): void
    {
        static::saving(function (self $person): void {
            if ($person->isDirty('national_id')) {
                $person->national_id_hash = filled($person->national_id)
                    ? BlindIndex::make((string) $person->national_id, self::BLIND_INDEX_CONTEXT)
                    : null;
            }
        });
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id');
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_code', 'code');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return static::query()->ownedBy($user)->whereKey($this->getKey())->exists();
    }

    /**
     * The people a user with `people.read_own` sees. A patient: only themself. Staff: themself, every staff member
     * (colleagues, employee profiles) and the patients whose files they may open — the patient-file rule itself, so
     * the two never drift apart. Without patient rights (HR) that is staff only.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        if (! $user instanceof User || $user->user_type === UserType::Patient) {
            return $query->whereKey($user->getAuthIdentifier());
        }
        $patients = OwnershipScope::apply(Patient::query()->select('patients.id'), 'patients', $user);

        return $query->where(fn (Builder $q) => $q
            ->whereKey($user->getAuthIdentifier())
            ->orWhereHas('user', fn (Builder $u) => $u->where('user_type', '!=', UserType::Patient))
            ->orWhereIn($q->qualifyColumn('id'), $patients));
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['national_id', 'national_id_hash'];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return Attribute<?string, ?string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim((string) $value)) : null);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (string $value): string => PhoneNumber::normalise($value));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function firstName(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::squish($value));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function lastName(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::squish($value));
    }

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'immutable_date',
            'national_id' => 'encrypted',
            'notification_opt_out' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
