<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HR\SettingKey;
use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Requests\Contracts\StaffRequestServiceInterface;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Modules\HR\Settings\Contracts\SettingServiceInterface;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\ClinicClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo staff requests: late excuses and overtime switched on (overtime paid at 2.500 BHD an hour), and the late
 * arrivals and unpaid overtime of the last weeks asked about — some waiting for HR, some approved, some rejected,
 * each decision with HR's reason.
 */
final class StaffRequestDemoSeeder extends Seeder
{
    private const LATE_REASONS = [
        'Heavy traffic on the causeway after an accident.',
        'My child was sick this morning; I took her to the GP first.',
        'The bus was late.',
        null,
        'Car battery died; waited for roadside help.',
        'Stayed late the night before to finish the discharge letters.',
        null,
        'Flooded road near Isa Town after the rain.',
    ];

    private const DECISIONS = [
        ['approve', null], ['approve', 'Confirmed with the traffic report.'], ['reject', 'Third late arrival this month; please plan for the traffic.'],
        ['approve', 'Family emergency — excused.'], ['reject', 'No reason given and not reported on the day.'],
    ];

    public function run(SettingServiceInterface $settings, StaffRequestServiceInterface $requests, ClinicClock $clock): void
    {
        if (app()->isProduction() && ! config('app.seed_demo')) {
            return;
        }
        $hr = User::query()->whereHas('person', fn ($q) => $q->where('email', 'hr'.DemoSeeder::EMAIL_DOMAIN))->first();
        if ($hr === null) {
            return;
        }
        $settings->save(SettingKey::LateExcuse, true);
        $settings->save(SettingKey::Overtime, true, '2.500');

        $recent = fn (string $column): Builder => Attendance::query()->with('employee.person')->where('employee_id', '!=', $hr->id)
            ->where('is_locked', false)->where($column, '>', 0)
            ->whereDate('date', '>=', $clock->today()->subDays(31)->toDateString())->orderByDesc('date');

        $asked = fn (StaffRequestType $type): bool => StaffRequest::query()->withDeleted()->where('type', $type->value)->exists();
        if (! $asked(StaffRequestType::Overtime)) {
            $this->stayLate($hr, $clock);
        }

        $late = $asked(StaffRequestType::LateExcuse) ? collect() : $recent('late_minutes')->where('late_excused', false)->limit(count(self::LATE_REASONS))->get();
        foreach ($late->values() as $i => $day) {
            $this->ask($requests, $day, StaffRequestType::LateExcuse, self::LATE_REASONS[$i], $i < 3 ? null : self::DECISIONS[($i - 3) % count(self::DECISIONS)], $hr);
        }
        $overtime = $asked(StaffRequestType::Overtime) ? collect() : $recent('overtime_minutes')->where('overtime_approved', false)->limit(3)->get();
        foreach ($overtime->values() as $i => $day) {
            $this->ask($requests, $day, StaffRequestType::Overtime, ['Stayed with an emergency patient until transfer.', 'Covered the evening nurse who called in sick.', null][$i], $i === 0 ? null : ($i === 1 ? ['approve', 'Thank you for covering.'] : ['reject', 'Overtime was not agreed with the head nurse.']), $hr);
        }
        Auth::forgetUser();
    }

    /** Three people stayed late on recent days (overtime not approved yet), so there is overtime to ask about. */
    private function stayLate(User $hr, ClinicClock $clock): void
    {
        $days = Attendance::query()->where('employee_id', '!=', $hr->id)->where('is_locked', false)->where('overtime_minutes', 0)
            ->whereNotNull('check_out_at')->whereDate('date', '>=', $clock->today()->subDays(10)->toDateString())
            ->orderByDesc('date')->get()->unique('employee_id')->take(3);
        foreach ($days->values() as $i => $day) {
            $extra = [95, 120, 75][$i];
            $day->forceFill([
                'check_out_at' => $day->check_out_at?->addMinutes($extra), 'worked_minutes' => $day->worked_minutes + $extra,
                'overtime_minutes' => $extra, 'overtime_approved' => false,
            ])->save();
        }
    }

    /** @param  array{0: string, 1: ?string}|null  $decision */
    private function ask(StaffRequestServiceInterface $requests, Attendance $day, StaffRequestType $type, ?string $reason, ?array $decision, User $hr): void
    {
        Auth::setUser(User::query()->findOrFail($day->employee_id));
        $request = $requests->create($day->employee, $type, $day->id, $reason);
        if ($decision === null) {
            return;
        }
        Auth::setUser($hr);
        [$action, $why] = $decision;
        $action === 'approve' ? $requests->approve($request, $why) : $requests->reject($request, (string) $why);
    }
}
