<?php

declare(strict_types=1);

namespace App\Shared\Bulk;

/**
 * Outcome of a bulk action (or its dry run). Partial success is normal: failures are listed per record.
 */
final class BulkResult
{
    /** @var list<array{id: string, code: string, message: string}> */
    private array $failed = [];

    /** @var list<string> */
    private array $succeededIds = [];

    /** Background task running a queued bulk action (poll GET /tasks/{id}). */
    public ?string $taskId = null;

    public function __construct(
        public readonly string $action,
        public readonly int $requested,
        public readonly bool $dryRun = false,
        public readonly bool $queued = false,
        public readonly ?string $inverse = null,
    ) {}

    public function succeed(string $id): void
    {
        $this->succeededIds[] = $id;
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function fail(string $id, string $code, string $message): void
    {
        $this->failed[] = ['id' => $id, 'code' => $code, 'message' => $message];
    }

    /**
     * `succeeded_ids` lets the client offer Undo (the inverse action) on exactly the changed records.
     *
     * @return array{action: string, requested: int, succeeded: int, succeeded_ids: list<string>, failed: list<array{id: string, code: string, message: string}>, dry_run: bool, queued: bool, inverse: ?string, task_id: ?string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'requested' => $this->requested,
            'succeeded' => count($this->succeededIds),
            'succeeded_ids' => $this->succeededIds,
            'failed' => $this->failed,
            'dry_run' => $this->dryRun,
            'queued' => $this->queued,
            'inverse' => $this->inverse,
            'task_id' => $this->taskId,
        ];
    }
}
