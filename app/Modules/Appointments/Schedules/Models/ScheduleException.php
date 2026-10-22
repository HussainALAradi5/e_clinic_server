<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Models;

use App\Enums\Appointments\ScheduleExceptionType;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date override of a provider's week (day off / custom hours) or a clinic-wide holiday (`provider_id` null).
 *
 * @property string $id
 * @property string $code
 * @property ?string $provider_id
 * @property CarbonImmutable $date
 * @property ScheduleExceptionType $type
 * @property ?string $start_time
 * @property ?string $end_time
 * @property ?string $reason
 * @property-read ?Employee $provider
 */
class ScheduleException extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'SEX';
    }

    public function isClinicWide(): bool
    {
        return $this->provider_id === null;
    }

    /** True when nobody (or this provider) works that day. */
    public function closesTheDay(): bool
    {
        return $this->type !== ScheduleExceptionType::CustomHours;
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
            'type' => ScheduleExceptionType::class,
            'date' => 'immutable_date',
        ];
    }
}
