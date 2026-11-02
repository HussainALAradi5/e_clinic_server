<?php

declare(strict_types=1);

namespace App\Tasks\Transfer;

use App\Dtos\Transfer\ExportData;
use App\Services\Shared\FileStorage;
use App\Services\Transfer\ExportService;
use App\Services\Transfer\TransferGuard;
use App\Tasks\Shared\TaskContext;
use App\Tasks\Shared\TaskHandler;
use App\Tasks\Shared\TaskOutcome;

/**
 * `list_export` — a large list export, run in the background with the requesting user's current rights.
 */
final class ListExportTaskHandler implements TaskHandler
{
    public function __construct(
        private readonly ExportService $exports,
        private readonly TransferGuard $guard,
        private readonly FileStorage $files,
    ) {}

    public function handle(TaskContext $task): TaskOutcome
    {
        $parameters = $task->parameters();
        $user = $task->user();
        $definition = $this->guard->forExport((string) $parameters['resource'], $user);
        $data = ExportData::fromArray($parameters);
        $columns = $this->exports->columns($definition, $data->columns);

        $total = max((clone $this->exports->query($definition, $data, $user))->count(), 1);
        $path = $this->files->newPath('exports', $data->format->value);

        $rows = $this->exports->write($definition, $data, $columns, $user, $this->files->absolutePath($path), function (int $done) use ($task, $total): bool {
            $task->progress((int) ($done / $total * 95));

            return ! $task->cancelled();
        });

        return new TaskOutcome($path, ['rows' => $rows], $this->exports->downloadName($definition, $data->format));
    }
}
