<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Interfaces\Shared\Auditable;
use App\Interfaces\Shared\OwnedByUser;
use App\Interfaces\Shared\TwoFactorAuthenticatable;
use App\Traits\Shared\Auditable as AuditsChanges;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\UserFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Login account of a person (shared primary key: users.id = persons.id). Account data only —
 * name, email and phone live on the person. Rights come from roles only, never from user_type.
 *
 * @property string $id
 * @property ?string $password
 * @property UserType $user_type
 * @property UserStatus $status
 * @property ?CarbonImmutable $email_verified_at
 * @property ?string $two_factor_secret
 * @property ?list<string> $two_factor_recovery_codes
 * @property ?CarbonImmutable $two_factor_confirmed_at
 * @property ?CarbonImmutable $last_login_at
 * @property ?string $last_login_ip
 * @property ?CarbonImmutable $locked_at
 * @property-read Person $person
 * @property-read Collection<int, Role> $roles
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements Auditable, OwnedByUser, TwoFactorAuthenticatable
{
    use AuditsChanges;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /** Spatie roles / permissions are defined for the API guard. */
    protected string $guard_name = 'sanctum';

    protected $fillable = ['id', 'password', 'user_type', 'status'];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $attributes = ['status' => 'active'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('users')
            ->sortable('code', 'last_name', 'first_name', 'email', 'status', 'user_type', 'last_login_at')
            ->filterable('user_type', enum: UserType::class, column: 'users.user_type')
            ->filterable('status', enum: UserStatus::class, column: 'users.status')
            ->filterable('email', ['eq', 'like'], column: 'persons.email')
            ->filterable('last_login_at', ['gte', 'lte', 'between', 'null'], column: 'users.last_login_at', type: 'datetime')
            ->searchIn('persons.first_name', 'persons.last_name', 'persons.code', 'persons.email', 'persons.phone')
            ->column('code', 'Code')
            ->column('full_name', 'Name')
            ->column('email', 'Email')
            ->column('user_type', 'Type')
            ->column('status', 'Status')
            ->column('last_login_at', 'Last login')
            ->dateFields('last_login_at')
            ->defaultSort('last_name,first_name');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id');
    }

    public function email(): ?string
    {
        return $this->person->email;
    }

    public function name(): string
    {
        return $this->person->full_name;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    /** Active accounts holding super_admin (the last one can be neither demoted nor deactivated). */
    public static function activeSuperAdminCount(): int
    {
        return static::role(RoleName::SuperAdmin->value)->where('status', UserStatus::Active)->count();
    }

    public function tokenLifetimeHours(): int
    {
        return Config::integer('clinic.auth.token_ttl_hours.'.($this->user_type->isPatient() ? 'patient' : 'staff'));
    }

    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(Config::array('access.two_factor_roles'));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email();
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email();
    }

    public function isOwnedBy(AuthenticatableContract $user): bool
    {
        return $this->getKey() === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, AuthenticatableContract $user): Builder
    {
        return $query->whereKey($user->getAuthIdentifier());
    }

    /**
     * Status, login, password and 2FA changes are audited by their own events (with reasons).
     *
     * @return list<string>
     */
    public function auditIgnored(): array
    {
        return ['status', 'locked_at', 'last_login_at', 'last_login_ip', 'password', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'email_verified_at'];
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'user_type' => UserType::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'immutable_datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
        ];
    }
}
