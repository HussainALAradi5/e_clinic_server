<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Console;

use App\Services\Shared\FileStorage;
use App\Tasks\Identity\Models\BackgroundTask;
use Illuminate\Console\Command;

/**
 * Deletes result files of tasks whose download window (expires_at) has passed. Scheduled daily.
 */
final class PurgeTaskResults extends Command
{
    protected $signature = 'tasks:purge-results';

    protected $description = 'Delete expired background-task result files';

    public function handle(FileStorage $files): int
    {
        $count = 0;
        BackgroundTask::query()->whereNotNull('result_path')->where('expires_at', '<', now())
            ->chunkById(200, function ($tasks) use ($files, &$count): void {
                foreach ($tasks as $task) {
                    $files->delete($task->result_path);
                    $task->forceFill(['result_path' => null])->saveQuietly();
                    $count++;
                }
            });

        $this->info("Purged {$count} result file(s).");

        return self::SUCCESS;
    }
}
