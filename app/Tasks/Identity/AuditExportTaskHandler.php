<?php

declare(strict_types=1);

namespace App\Tasks\Identity;

use App\Models\Identity\AuditLog;
use App\Services\Shared\FileStorage;
use App\Tasks\Shared\TaskContext;
use App\Tasks\Shared\TaskHandler;
use App\Tasks\Shared\TaskOutcome;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use RuntimeException;

/**
 * `audit_export` — the filtered audit trail as CSV (same list query and ownership rules as GET /audit-logs).
 */
final class AuditExportTaskHandler implements TaskHandler
{
    private const CHUNK = 1000;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly FileStorage $files,
    ) {}

    public function handle(TaskContext $task): TaskOutcome
    {
        /** @var array<string, mixed> $params */
        $params = (array) ($task->parameters()['query'] ?? []);
        $query = ListQuery::fromArray($params, AuditLog::listQueryConfig());
        $builder = $this->listQuery->apply(OwnershipScope::apply(AuditLog::query()->with('user.person'), 'audit', $task->user()), $query);

        $total = max((clone $builder)->count(), 1);
        $path = $this->files->newPath('exports', 'csv');
        $this->files->disk()->put($path, '');
        $out = fopen($this->files->absolutePath($path), 'wb');
        if ($out === false) {
            throw new RuntimeException('Cannot write export file.');
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['time', 'user', 'action', 'module', 'record_type', 'record_id', 'old_values', 'new_values', 'ip_address', 'request_id']);
        $done = 0;

        foreach ($builder->lazyById(self::CHUNK) as $log) {
            /** @var AuditLog $log */
            fputcsv($out, [
                $log->created_at->toIso8601String(),
                $log->user?->person->full_name ?? 'system',
                $log->action->value,
                $log->module,
                $log->auditableAlias() ?? $log->auditable_type,
                $log->auditable_id,
                $log->old_values !== null ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                $log->new_values !== null ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
                $log->ip_address,
                $log->request_id,
            ]);

            if (++$done % self::CHUNK === 0) {
                if ($task->cancelled()) {
                    break;
                }
                $task->progress((int) ($done / $total * 95));
            }
        }
        fclose($out);

        return new TaskOutcome($path, ['rows' => $done], 'audit-log-'.now()->format('Ymd-His').'.csv');
    }
}
