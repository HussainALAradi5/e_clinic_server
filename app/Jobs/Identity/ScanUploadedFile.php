<?php

declare(strict_types=1);

namespace App\Jobs\Identity;

use App\Enums\Identity\FileScanStatus;
use App\Interfaces\Identity\FileScanner;
use App\Models\Identity\Attachment;
use App\Services\Shared\FileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Antivirus scan of a new attachment. Infected files are quarantined (deleted from storage, row kept as `infected`).
 */
final class ScanUploadedFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $attachmentId) {}

    public function handle(FileScanner $scanner, FileStorage $files): void
    {
        $attachment = Attachment::query()->withoutGlobalScopes()->find($this->attachmentId);
        if ($attachment === null || $attachment->scan_status !== FileScanStatus::Pending) {
            return;
        }

        $status = $scanner->scan($files->absolutePath($attachment->path));
        $attachment->forceFill(['scan_status' => $status])->saveQuietly();

        if ($status === FileScanStatus::Infected) {
            $files->delete($attachment->path);
            Log::warning('Infected attachment quarantined', ['attachment' => $attachment->id, 'code' => $attachment->code]);
        }
    }
}
