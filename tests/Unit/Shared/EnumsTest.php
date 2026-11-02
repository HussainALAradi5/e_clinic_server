<?php

use App\Enums\Shared\DateRangePreset;
use App\Enums\Shared\DayOfWeek;
use App\Enums\Shared\GenericStatus;
use App\Enums\Shared\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rules\Enum;

it('exposes values, options and a validation rule', function () {
    expect(PaymentMethod::values())->toContain('benefit_pay')
        ->and(GenericStatus::options()[1])->toBe(['value' => 'in_progress', 'label' => 'In progress'])
        ->and(DayOfWeek::Sunday->label())->toBe('Sunday')
        ->and(GenericStatus::rule())->toBeInstanceOf(Enum::class);
});

it('guards GenericStatus transitions', function () {
    expect(GenericStatus::Pending->canTransitionTo(GenericStatus::InProgress))->toBeTrue()
        ->and(GenericStatus::Pending->canTransitionTo(GenericStatus::Completed))->toBeFalse()
        ->and(GenericStatus::Completed->isFinal())->toBeTrue()
        ->and(GenericStatus::InProgress->isFinal())->toBeFalse();
});

it('resolves date range presets with Sunday-based weeks', function (DateRangePreset $preset, string $from, string $to) {
    [$start, $end] = $preset->range(CarbonImmutable::parse('2026-10-13'));

    expect($start->toDateString())->toBe($from)->and($end->toDateString())->toBe($to);
})->with([
    [DateRangePreset::Today, '2026-10-13', '2026-10-13'],
    [DateRangePreset::ThisWeek, '2026-10-11', '2026-10-17'],
    [DateRangePreset::LastWeek, '2026-10-04', '2026-10-10'],
    [DateRangePreset::LastMonth, '2026-09-01', '2026-09-30'],
    [DateRangePreset::Last7Days, '2026-10-07', '2026-10-13'],
    [DateRangePreset::ThisQuarter, '2026-10-01', '2026-12-31'],
]);
