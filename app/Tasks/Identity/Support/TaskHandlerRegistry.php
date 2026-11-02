<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Support;

use App\Enums\Identity\TaskType;
use App\Tasks\Shared\TaskHandler;
use LogicException;

/**
 * TaskType → handler class. Each module registers its handlers in its ServiceProvider.
 */
final class TaskHandlerRegistry
{
    /** @var array<string, class-string<TaskHandler>> */
    private array $handlers = [];

    /**
     * @param  class-string<TaskHandler>  $handler
     */
    public function register(TaskType $type, string $handler): void
    {
        $this->handlers[$type->value] = $handler;
    }

    public function has(TaskType $type): bool
    {
        return isset($this->handlers[$type->value]);
    }

    public function resolve(TaskType $type): TaskHandler
    {
        $class = $this->handlers[$type->value] ?? throw new LogicException("No handler registered for task type [{$type->value}].");

        return app($class);
    }
}
