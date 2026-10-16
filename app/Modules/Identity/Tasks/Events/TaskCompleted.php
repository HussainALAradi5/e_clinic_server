<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Events;

use App\Modules\Identity\Tasks\Models\BackgroundTask;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task finished; its owner is notified (in-app).
 */
final class TaskCompleted
{
    use Dispatchable;

    public function __construct(public readonly BackgroundTask $task) {}
}
