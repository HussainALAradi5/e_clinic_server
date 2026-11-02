<?php

declare(strict_types=1);

namespace App\Tasks\Shared;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a running background task can see and do (implemented by the Identity background-task runner).
 */
interface TaskContext
{
    public function id(): string;

    /**
     * Validated input stored with the task.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /** The user who started the task; handlers act with this user's rights. */
    public function user(): Authenticatable;

    /** 0–100; throttled writes. */
    public function progress(int $percent): void;

    /** True when the user cancelled the task — handlers check it between chunks and stop. */
    public function cancelled(): bool;
}
