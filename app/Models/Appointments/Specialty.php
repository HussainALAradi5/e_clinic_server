<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medical specialty (code entered by staff, e.g. `GP`).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $name_ar
 * @property ?string $description
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Specialty extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'SPC';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('specialties')
            ->sortable('code', 'name', 'created_at')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('doctors_count', 'Doctors')
            ->dateFields('created_at')
            ->defaultSort('name');
    }

    /**
     * @return HasMany<Doctor, $this>
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
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
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
