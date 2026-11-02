<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Enums\Identity\FileScanStatus;

/**
 * Antivirus check of a stored file (absolute local path).
 */
interface FileScanner
{
    public function scan(string $absolutePath): FileScanStatus;
}
