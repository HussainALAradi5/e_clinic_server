<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Data;

use App\Modules\Identity\Attachments\Models\Attachment;

/**
 * Per-file outcome of a multi-file upload: saved files (duplicates flagged) and failures.
 */
final class UploadResult
{
    /** @var list<Attachment> */
    public array $saved = [];

    /** @var list<array{index: int, name: string, code: string, message: string}> */
    public array $failed = [];

    /** 201 all saved, 207 partial, 422 none. */
    public function httpStatus(): int
    {
        return match (true) {
            $this->failed === [] => 201,
            $this->saved === [] => 422,
            default => 207,
        };
    }
}
