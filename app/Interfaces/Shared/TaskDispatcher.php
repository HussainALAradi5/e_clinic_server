<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Starts a background task (`202 Accepted` endpoints). Implemented by the Identity BackgroundTaskService,
 * so shared code (bulk actions, exports) never depends on a module.
 */
interface TaskDispatcher
{
    /**
     * @param  string  $type  TaskType value, e.g. `bulk_action`
     * @param  array<string, mixed>  $parameters
     * @return Model the created task (its id is returned to the client for polling)
     */
    public function start(string $type, array $parameters, Authenticatable $user, ?Model $subject = null): Model;
}
