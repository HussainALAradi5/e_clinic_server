<?php

declare(strict_types=1);

namespace App\Utilities\HR;

use App\Dtos\HR\DoctorEarning;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\HR\CommissionType;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Doctor;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * A doctor's earnings for a period, the same for the payslip and the earnings report. Revenue comes from the
 * ledger (lines tagged with the doctor on revenue accounts, posted), so cancellations and returns reduce it and
 * package sessions count when used.
 */
final class DoctorEarnings
{
    public static function of(Doctor $doctor, CarbonImmutable $from, CarbonImmutable $to): DoctorEarning
    {
        $lines = JournalEntryLine::query()
            ->where('doctor_id', $doctor->id)
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::Revenue->value))
            ->whereHas('entry', fn ($q) => $q->where('status', JournalEntryStatus::Posted->value)
                ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()]))
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS net')
            ->value('net');
        $revenue = MoneyHelper::of((string) ($lines ?? '0'));
        $visits = Appointment::query()
            ->where('provider_id', $doctor->id)
            ->where('status', AppointmentStatus::Completed->value)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->count();
        $rate = (string) $doctor->commission_value;
        $commission = match ($doctor->commission_type) {
            CommissionType::Percent => $revenue->isPositive() ? $revenue->multipliedBy($rate, RoundingMode::HALF_UP)->dividedBy(100, RoundingMode::HALF_UP) : MoneyHelper::zero(),
            CommissionType::PerVisit => MoneyHelper::of($rate)->multipliedBy($visits),
            CommissionType::None => MoneyHelper::zero(),
        };

        return new DoctorEarning($doctor->id, $doctor->person->full_name, $doctor->commission_type, $rate, $revenue, $visits, $commission);
    }
}
