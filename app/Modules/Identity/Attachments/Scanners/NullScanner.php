<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Scanners;

use App\Enums\Identity\FileScanStatus;
use App\Modules\Identity\Attachments\Contracts\FileScanner;

/**
 * No antivirus (development / tests): every file is clean.
 */
final class NullScanner implements FileScanner
{
    public function scan(string $absolutePath): FileScanStatus
    {
        return FileScanStatus::Clean;
    }
}
