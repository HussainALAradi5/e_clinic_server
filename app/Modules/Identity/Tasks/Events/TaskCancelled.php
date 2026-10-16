<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Events;

use App\Modules\Identity\Tasks\Models\BackgroundTask;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task was cancelled by its owner or ended as failed / timed out (owner notified when the system stopped it).
 */
final class TaskCancelled
{
    use Dispatchable;

    public function __construct(public readonly BackgroundTask $task) {}
}
