<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dtos\HR\LeaveRequestData;
use App\Dtos\HR\PayrollPaymentData;
use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Enums\HR\SalaryComponentType;
use App\Exceptions\Shared\DomainException;
use App\Interfaces\HR\LeaveServiceInterface;
use App\Interfaces\HR\PayrollServiceInterface;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Identity\User;
use App\Utilities\HR\WorkCalendar;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Demo HR (Phase 6), through the real services where it matters: allowances and a staff loan; last month's and this
 * month's attendance (a few late arrivals, an absence, approved overtime and some waiting for approval); leave taken
 * last month (emergency, unpaid), one waiting for approval, one approved ahead and one rejected; the month before
 * last paid and last month's payroll calculated, waiting for the accountant.
 */
final class HrDemoSeeder extends Seeder
{
    /** email prefix => [type, name, amount, taxable] */
    private const COMPONENTS = [
        'doctor' => [['allowance', 'Housing allowance', '250', false], ['allowance', 'Transport allowance', '50', false]],
        'doctor2' => [['allowance', 'Housing allowance', '250', false], ['allowance', 'Transport allowance', '50', false]],
        'nurse' => [['allowance', 'Housing allowance', '150', false]],
        'pharmacist' => [['allowance', 'Transport allowance', '40', false]],
        'reception' => [['allowance', 'Transport allowance', '30', false], ['deduction', 'Staff loan repayment', '40', false]],
    ];

    /** Expatriate staff (1 % social insurance instead of the Bahraini 7 %). */
    private const NATIONALITIES = ['nurse' => 'PH', 'pharmacist' => 'IN', 'doctor' => 'JO'];

    public function run(LeaveServiceInterface $leaves, PayrollServiceInterface $payroll, WorkCalendar $calendar, ClinicClock $clock): void
    {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Attendance::query()->exists() || LeaveRequest::query()->withDeleted()->exists()) {
            return;
        }
        $hr = $this->user('hr');
        $accountant = $this->user('accountant');
        if ($hr === null || $accountant === null) {
            return;
        }
        $this->call(LeaveTypeSeeder::class);
        $today = $clock->today();
        $lastMonth = $today->startOfMonth()->subMonthNoOverflow();
        /** @var Collection<string, Employee> $staff */
        $staff = Employee::query()->with('person')->get()->keyBy(fn (Employee $e): string => strstr((string) $e->person->email, '@', true) ?: $e->id);
        if (! $staff->has(['cashier', 'pharmacist', 'nurse', 'reception', 'accountant'])) {
            return;
        }
        $member = fn (string $who): Employee => $staff->get($who) ?? throw new LogicException("Demo staff {$who} is missing.");

        foreach (self::COMPONENTS as $who => $components) {
            foreach ($components as [$type, $name, $amount, $taxable]) {
                if ($staff->has($who)) {
                    EmployeeSalaryComponent::query()->create([
                        'employee_id' => $member($who)->id, 'type' => SalaryComponentType::from($type), 'name' => $name, 'amount' => $amount,
                        'effective_from' => $lastMonth->subMonthsNoOverflow(3)->toDateString(), 'is_taxable' => $taxable,
                        'effective_to' => $type === 'deduction' ? $today->addMonthsNoOverflow(6)->endOfMonth()->toDateString() : null,
                    ]);
                }
            }
        }
        foreach (self::NATIONALITIES as $who => $country) {
            $staff->get($who)?->person->forceFill(['nationality_code' => $country])->save();
        }

        // Leave: taken last month, waiting, approved ahead, rejected.
        $type = fn (string $code): string => (string) LeaveType::query()->where('code', $code)->value('id');
        $workday = fn (Employee $e, CarbonImmutable $from, int $n): CarbonImmutable => $calendar->workingDays($e, $from, $from->addMonths(2))[$n];
        $plans = [
            ['cashier', 'EMERGENCY', $workday($member('cashier'), $lastMonth, 4), 0, 'Family emergency', 'approve'],
            ['pharmacist', 'UNPAID', $workday($member('pharmacist'), $lastMonth, 7), 0, 'Personal matter', 'approve'],
            ['nurse', 'ANNUAL', $workday($member('nurse'), $today->addWeeks(3), 0), 2, 'Visiting family in Manila', null],
            ['reception', 'ANNUAL', $workday($member('reception'), $today->addWeeks(5), 0), 4, 'Holiday', 'approve'],
            ['accountant', 'ANNUAL', $workday($member('accountant'), $today->addWeeks(1), 0), 1, 'Short break', 'reject'],
        ];
        foreach ($plans as [$who, $code, $start, $extra, $reason, $decision]) {
            $employee = $member($who);
            $end = $extra === 0 ? $start : $calendar->workingDays($employee, $start, $start->addWeeks(2))[$extra];
            // Leave already taken is recorded by HR; future leave is asked for by the employee.
            Auth::setUser($start->lt($today) ? $hr : User::query()->findOrFail($employee->id));
            $request = $leaves->request($employee, new LeaveRequestData($type($code), $start, $end, reason: $reason));
            Auth::setUser($hr);
            match ($decision) {
                'approve' => $leaves->approve($request, 'Approved'),
                'reject' => $leaves->reject($request, 'Month-end closing — please pick another week'),
                default => null,
            };
        }

        $this->attendance(array_values($staff->all()), $lastMonth, $today, $calendar, $clock);

        // Payroll: the month before last paid, last month calculated and waiting for the accountant.
        Auth::setUser($hr);
        $previous = $lastMonth->subMonthNoOverflow();
        try {
            $run = $payroll->calculate($payroll->createRun($previous->year, $previous->month));
            Auth::setUser($accountant);
            $payroll->markPaid($payroll->approve($run), new PayrollPaymentData($lastMonth->addDays(1), 'WPS-'.$previous->format('Y-m')));
        } catch (DomainException) {
            // The month before last is in a fiscal year that is not open (early in the year): leave it out.
        }
        Auth::setUser($hr);
        $payroll->calculate($payroll->createRun($lastMonth->year, $lastMonth->month));
        Auth::forgetUser();
    }

    /**
     * Working days from the first of last month up to today: mostly on time, some late arrivals, one absence, a few
     * evenings of overtime (approved last month, waiting this month); today some staff have already checked in.
     *
     * @param  list<Employee>  $staff
     */
    private function attendance(array $staff, CarbonImmutable $from, CarbonImmutable $today, WorkCalendar $calendar, ClinicClock $clock): void
    {
        $holidays = $calendar->holidays($from, $today);
        $leave = LeaveRequest::query()->where('status', 'approved')->get();
        $nowLocal = $clock->toClinic($clock->now())->format('H:i');
        foreach ($staff as $i => $employee) {
            $email = (string) $employee->person->email;
            foreach (CarbonPeriod::create($from->toDateString(), $today->toDateString()) as $n => $day) {
                $day = CarbonImmutable::instance($day);
                $isToday = $day->toDateString() === $today->toDateString();
                $off = $calendar->dayOff($employee, $day, $holidays);
                $onLeave = $leave->first(fn (LeaveRequest $r): bool => $r->employee_id === $employee->id && $r->start_date->toDateString() <= $day->toDateString() && $r->end_date->toDateString() >= $day->toDateString());
                if ($isToday && ($off !== null || $onLeave !== null || $nowLocal < '08:05' || ($i % 3) === 2)) {
                    continue;
                }
                if ($onLeave !== null) {
                    continue; // marked on leave when the leave was approved
                }
                if ($off !== null) {
                    Attendance::query()->create(['employee_id' => $employee->id, 'date' => $day->toDateString(), 'status' => $off, 'source' => AttendanceSource::Manual]);

                    continue;
                }
                if (str_starts_with($email, 'pharmacist@') && $n === 15 && $day->month === $from->month) {
                    Attendance::query()->create(['employee_id' => $employee->id, 'date' => $day->toDateString(), 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Manual]);

                    continue;
                }
                $late = ($n + $i) % 11 === 0 ? 20 + ($i % 3) * 7 : 0;
                $overtime = str_starts_with($email, 'nurse@') && in_array($day->day, [6, 13, 20], true) ? 90 : 0;
                $in = $clock->combine($day, $late > 0 ? sprintf('08:%02d', $late) : sprintf('07:%02d', 50 + ($n + $i) % 10));
                $out = $isToday ? null : $clock->combine($day, $overtime > 0 ? '17:30' : sprintf('16:%02d', ($n * 3 + $i) % 10));
                Attendance::query()->create([
                    'employee_id' => $employee->id,
                    'date' => $day->toDateString(),
                    'check_in_at' => $in,
                    'check_out_at' => $out,
                    'worked_minutes' => $out === null ? 0 : intdiv($out->getTimestamp() - $in->getTimestamp(), 60),
                    'late_minutes' => $late,
                    'overtime_minutes' => $overtime,
                    'overtime_approved' => $overtime > 0 && $day->month === $from->month,
                    'status' => $late > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
                    'source' => AttendanceSource::Web,
                    'check_in_ip' => '192.168.10.'.(20 + $i),
                ]);
            }
        }
    }

    private function user(string $name): ?User
    {
        return User::query()->whereHas('person', fn ($q) => $q->where('email', $name.DemoSeeder::EMAIL_DOMAIN))->first();
    }
}
