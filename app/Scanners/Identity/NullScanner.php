<?php

declare(strict_types=1);

namespace App\Scanners\Identity;

use App\Enums\Identity\FileScanStatus;
use App\Interfaces\Identity\FileScanner;

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
