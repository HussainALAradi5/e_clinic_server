<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Models;

use App\Enums\HR\ShiftType;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A work shift (`SFT-000001`). Fixed: late after `start_time`, overtime after `end_time`. Flexible: arrive between
 * `start_time` and `latest_start`, leave by `end_time`; overtime is time worked beyond `required_minutes`.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ShiftType $type
 * @property string $start_time
 * @property string $end_time
 * @property ?string $latest_start
 * @property int $required_minutes
 * @property ?string $notes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Shift extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'SFT';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('shifts')
            ->sortable('code', 'name', 'start_time', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: ShiftType::class)
            ->searchIn('code', 'name')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('type', 'Type')
            ->column('start_time', 'From')
            ->column('end_time', 'To')
            ->column('employees_count', 'Employees')
            ->dateFields('created_at')
            ->defaultSort('start_time,name');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** The time someone is late after (before the grace period): the start, or the latest start when flexible. */
    public function arriveBy(): string
    {
        return substr($this->type === ShiftType::Flexible ? (string) $this->latest_start : $this->start_time, 0, 5);
    }

    /** Fixed: the end; flexible: when the hours due are done for someone arriving at the latest start. */
    public function expectedEnd(): string
    {
        if ($this->type === ShiftType::Fixed) {
            return substr($this->end_time, 0, 5);
        }
        [$h, $m] = array_map(intval(...), explode(':', $this->arriveBy()));
        $end = min($h * 60 + $m + $this->required_minutes, 23 * 60 + 59);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
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
            'type' => ShiftType::class,
            'required_minutes' => 'integer',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
