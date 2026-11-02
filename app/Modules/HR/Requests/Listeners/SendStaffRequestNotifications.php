<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Listeners;

use App\Modules\HR\Requests\Events\StaffRequestChanged;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Support\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A new request reaches the reviewers (never the requester); the decision, with the reviewer's reason, reaches the
 * requester.
 */
final class SendStaffRequestNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(StaffRequestChanged $event): void
    {
        $request = $event->request->loadMissing('employee.person', 'attendance');
        $minutes = $request->minutes();
        $data = [
            'code' => $request->code,
            'employee' => $request->employee->person->full_name,
            'type' => mb_strtolower($request->type->label()),
            'date' => $request->attendance->date->toDateString(),
            'minutes' => $minutes >= 60 ? sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60) : "{$minutes} min",
            'reason' => (string) $request->review_reason,
        ];
        [$template, $people] = match ($request->status) {
            ApprovalStatus::Pending => ['staff_request.requested', Recipients::withPermission('staff_requests.review', [$request->employee_id])],
            ApprovalStatus::Approved => ['staff_request.approved', Recipients::user($request->employee_id)],
            ApprovalStatus::Rejected => ['staff_request.rejected', Recipients::user($request->employee_id)],
            default => [null, collect()],
        };
        if ($template === null) {
            return;
        }
        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $request, $data));
    }
}
