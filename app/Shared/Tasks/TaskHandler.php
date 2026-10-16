<?php

declare(strict_types=1);

namespace App\Shared\Tasks;

/**
 * The work behind one TaskType (export, ZIP, bulk action …). Registered with the task registry by each module;
 * runs on the queue with the requesting user's rights.
 */
interface TaskHandler
{
    public function handle(TaskContext $task): TaskOutcome;
}
