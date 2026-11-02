<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Models\Identity\BackgroundTask;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task finished; its owner is notified (in-app).
 */
final class TaskCompleted
{
    use Dispatchable;

    public function __construct(public readonly BackgroundTask $task) {}
}
