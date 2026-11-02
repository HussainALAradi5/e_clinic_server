<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Enums\Identity\TaskCancelReason;
use App\Events\Identity\TaskCancelled;
use App\Events\Identity\TaskCompleted;
use App\Interfaces\Identity\NotificationServiceInterface;

/**
 * In-app notification to the user who started the task — on success, failure or timeout (not when they cancelled it).
 */
final class NotifyTaskOwner
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(TaskCompleted|TaskCancelled $event): void
    {
        $task = $event->task;
        $person = $task->requester?->person;
        if ($person === null || $task->cancel_reason === TaskCancelReason::User) {
            return;
        }

        $failed = $event instanceof TaskCancelled;
        $this->notifications->send($failed ? 'task.failed' : 'task.completed', $person, $task, [
            'task' => $task->type->label(),
            'reason' => $task->error_message,
        ]);
    }
}
