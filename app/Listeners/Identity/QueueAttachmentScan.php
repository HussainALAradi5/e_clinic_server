<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Events\Identity\AttachmentUploaded;
use App\Jobs\Identity\ScanUploadedFile;

final class QueueAttachmentScan
{
    public function handle(AttachmentUploaded $event): void
    {
        ScanUploadedFile::dispatch($event->attachment->id)->afterCommit();
    }
}
