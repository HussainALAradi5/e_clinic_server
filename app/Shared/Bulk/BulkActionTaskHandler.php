<?php

declare(strict_types=1);

namespace App\Shared\Bulk;

use App\Shared\Exceptions\BulkActionNotAllowedException;
use App\Shared\Tasks\TaskContext;
use App\Shared\Tasks\TaskHandler;
use App\Shared\Tasks\TaskOutcome;

/**
 * Runs a large bulk action (> clinic.bulk.sync_limit targets) as background task `bulk_action`,
 * with the requesting user's rights, reporting progress per chunk and stopping when cancelled.
 */
final class BulkActionTaskHandler implements TaskHandler
{
    public const TYPE = 'bulk_action';

    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly BulkActionService $service,
    ) {}

    public function handle(TaskContext $task): TaskOutcome
    {
        $params = $task->parameters();
        $definition = $this->registry->for((string) $params['resource']);
        $action = $definition->find((string) $params['action']) ?? throw new BulkActionNotAllowedException;
        /** @var list<string> $ids */
        $ids = array_values(array_map('strval', (array) $params['ids']));
        $total = max(count($ids), 1);

        $result = $this->service->process(
            $definition,
            $action,
            $ids,
            isset($params['reason']) ? (string) $params['reason'] : null,
            $task->user(),
            new BulkResult($action->name, count($ids), inverse: $action->inverse),
            function (int $done) use ($task, $total): bool {
                $task->progress(intdiv($done * 100, $total));

                return ! $task->cancelled();
            },
        );

        $summary = $result->toArray();

        return new TaskOutcome(summary: [
            'action' => $summary['action'],
            'requested' => $summary['requested'],
            'succeeded' => $summary['succeeded'],
            'failed' => array_slice($summary['failed'], 0, 100),
            'failed_count' => $result->failedCount(),
        ]);
    }
}
