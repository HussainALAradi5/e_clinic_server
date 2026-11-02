<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Events;

use App\Tasks\Identity\Models\BackgroundTask;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task finished; its owner is notified (in-app).
 */
final class TaskCompleted
{
    use Dispatchable;

    public function __construct(public readonly BackgroundTask $task) {}
}
