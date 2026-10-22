<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Listeners;

use App\Modules\HR\Leaves\Events\LeaveStatusChanged;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Support\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Leave workflow in the app: a request sent for approval reaches the approvers (never the requester); the decision
 * reaches the requester.
 */
final class SendLeaveNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(LeaveStatusChanged $event): void
    {
        $request = $event->request->loadMissing('employee.person', 'leaveType');
        $data = [
            'code' => $request->code,
            'employee' => $request->employee->person->full_name,
            'type' => $request->leaveType->name,
            'from' => $request->start_date->toDateString(),
            'to' => $request->end_date->toDateString(),
            'days' => (string) $request->total_days,
            'notes' => (string) $request->review_notes,
        ];
        [$template, $people] = match ($request->status) {
            ApprovalStatus::Pending => ['leave.requested', Recipients::withPermission('leaves.approve', [$request->employee_id])],
            ApprovalStatus::Approved => ['leave.approved', Recipients::user($request->employee_id)],
            ApprovalStatus::Rejected => ['leave.rejected', Recipients::user($request->employee_id)],
            default => [null, collect()],
        };
        if ($template === null) {
            return;
        }
        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $request, $data));
    }
}
