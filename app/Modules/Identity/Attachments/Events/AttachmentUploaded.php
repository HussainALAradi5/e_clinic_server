<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Events;

use App\Modules\Identity\Attachments\Models\Attachment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A file was stored (its antivirus scan is queued).
 */
final class AttachmentUploaded
{
    use Dispatchable;

    public function __construct(public readonly Attachment $attachment) {}
}
