<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Models\Identity\Attachment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A file was stored (its antivirus scan is queued).
 */
final class AttachmentUploaded
{
    use Dispatchable;

    public function __construct(public readonly Attachment $attachment) {}
}
