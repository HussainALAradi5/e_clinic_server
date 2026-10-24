<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Listeners;

use App\Enums\HR\PayrollStatus;
use App\Modules\HR\Payroll\Events\PayrollStatusChanged;
use App\Modules\HR\Payroll\Models\Payslip;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Shared\Support\MoneyHelper;
use App\Shared\Support\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A calculated payroll reaches those who approve it (not who calculated it); once paid, every employee hears their
 * payslip is ready.
 */
final class SendPayrollNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(PayrollStatusChanged $event): void
    {
        $run = $event->run;
        $month = $run->periodStart()->format('F Y');
        if ($run->status === PayrollStatus::Calculated) {
            $data = ['code' => $run->code, 'month' => $month, 'net' => MoneyHelper::format($run->total_net), 'count' => (string) $run->employee_count];
            Recipients::withPermission('payroll.approve', [$run->calculated_by])->unique('id')
                ->each(fn (Person $p) => $this->notifications->send('payroll.calculated', $p, $run, $data));
        }
        if ($run->status === PayrollStatus::Paid) {
            $run->payslips()->with('employee.person.user')->get()->each(fn (Payslip $slip) => $this->notifications->send(
                'payroll.payslip_ready', $slip->employee->person, $slip, ['month' => $month, 'net' => MoneyHelper::format($slip->net_salary)],
            ));
        }
    }
}
