<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Tasks;

use App\Modules\Identity\Attachments\Contracts\AttachmentServiceInterface;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Shared\Files\FileStorage;
use App\Shared\Tasks\TaskContext;
use App\Shared\Tasks\TaskHandler;
use App\Shared\Tasks\TaskOutcome;
use RuntimeException;
use ZipArchive;

/**
 * `attachments_zip` — packs the selected files (still readable and clean for the requester) into one ZIP.
 */
final class AttachmentsZipTaskHandler implements TaskHandler
{
    public function __construct(
        private readonly AttachmentServiceInterface $attachments,
        private readonly FileStorage $files,
    ) {}

    public function handle(TaskContext $task): TaskOutcome
    {
        /** @var list<string> $ids */
        $ids = (array) ($task->parameters()['attachment_ids'] ?? []);
        $path = $this->files->newPath('exports', 'zip');
        $zip = new ZipArchive;

        if ($zip->open($this->files->absolutePath($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ZIP file.');
        }

        $names = [];
        $skipped = [];
        foreach ($ids as $i => $id) {
            if ($task->cancelled()) {
                $zip->close();

                return new TaskOutcome($path);
            }

            $attachment = Attachment::query()->find($id);
            try {
                if ($attachment === null) {
                    throw new RuntimeException('not found');
                }
                $this->attachments->assertDownloadable($attachment);
                $zip->addFile($this->files->absolutePath($attachment->path), $this->uniqueName($attachment->original_name, $names));
            } catch (\Throwable) {
                $skipped[] = $id;
            }
            $task->progress((int) (($i + 1) / max(count($ids), 1) * 95));
        }
        $zip->close();

        return new TaskOutcome($path, ['files' => count($ids) - count($skipped), 'skipped' => $skipped], 'attachments-'.now()->format('Ymd-His').'.zip');
    }

    /**
     * @param  array<string, true>  $names
     */
    private function uniqueName(string $name, array &$names): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        for ($n = 2; isset($names[$candidate]); $n++) {
            $candidate = "{$base} ({$n})".($ext !== '' ? ".{$ext}" : '');
        }
        $names[$candidate] = true;

        return $candidate;
    }
}
