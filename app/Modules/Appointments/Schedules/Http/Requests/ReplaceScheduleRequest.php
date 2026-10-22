<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Http\Requests;

use App\Modules\Appointments\Schedules\Data\ScheduleBlockData;
use App\Shared\Enums\DayOfWeek;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceScheduleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
            'blocks' => ['present', 'array', 'max:50'],
            'blocks.*.day_of_week' => ['required', 'integer', Rule::enum(DayOfWeek::class)],
            'blocks.*.start_time' => ['required', 'date_format:H:i'],
            'blocks.*.end_time' => ['required', 'date_format:H:i', 'after:blocks.*.start_time'],
            'blocks.*.slot_minutes' => ['nullable', 'integer', 'between:5,240'],
        ];
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('effective_from')->toString())->startOfDay();
    }

    /**
     * @return list<ScheduleBlockData>
     */
    public function blocks(): array
    {
        /** @var list<array{day_of_week: int|string, start_time: string, end_time: string, slot_minutes?: int|string|null}> $blocks */
        $blocks = $this->validated('blocks');

        return array_map(fn (array $b): ScheduleBlockData => new ScheduleBlockData(
            DayOfWeek::from((int) $b['day_of_week']),
            $b['start_time'],
            $b['end_time'],
            isset($b['slot_minutes']) ? (int) $b['slot_minutes'] : null,
        ), $blocks);
    }
}
