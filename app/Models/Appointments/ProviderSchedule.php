<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Enums\Shared\DayOfWeek;
use App\Models\Base\BaseModel;
use App\Models\HR\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weekly working block of someone who takes bookings (clinic local time), valid from / to a date.
 *
 * @property string $id
 * @property string $code
 * @property string $provider_id
 * @property DayOfWeek $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property ?int $slot_minutes
 * @property CarbonImmutable $effective_from
 * @property ?CarbonImmutable $effective_to
 * @property-read Employee $provider
 */
class ProviderSchedule extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'SCH';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    /**
     * Blocks valid on a date (any weekday when `$date` is null → valid today or later).
     *
     * @param  Builder<ProviderSchedule>  $query
     * @return Builder<ProviderSchedule>
     */
    public function scopeEffectiveOn(Builder $query, CarbonImmutable $date): Builder
    {
        $day = $date->toDateString();

        return $query->whereDate('effective_from', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'provider_id');
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'slot_minutes' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }
}
