<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Listeners;

use App\Modules\Identity\Attachments\Events\AttachmentUploaded;
use App\Modules\Identity\Attachments\Jobs\ScanUploadedFile;

final class QueueAttachmentScan
{
    public function handle(AttachmentUploaded $event): void
    {
        ScanUploadedFile::dispatch($event->attachment->id)->afterCommit();
    }
}
