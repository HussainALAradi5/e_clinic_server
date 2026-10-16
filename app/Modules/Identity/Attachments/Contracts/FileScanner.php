<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Contracts;

use App\Enums\Identity\FileScanStatus;

/**
 * Antivirus check of a stored file (absolute local path).
 */
interface FileScanner
{
    public function scan(string $absolutePath): FileScanStatus;
}
