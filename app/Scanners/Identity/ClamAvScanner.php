<?php

declare(strict_types=1);

namespace App\Scanners\Identity;

use App\Enums\Identity\FileScanStatus;
use App\Interfaces\Identity\FileScanner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * ClamAV through clamdscan: exit 0 = clean, 1 = infected, 2 = error (the job retries).
 */
final class ClamAvScanner implements FileScanner
{
    public function scan(string $absolutePath): FileScanStatus
    {
        $result = Process::timeout(300)->run([Config::string('attachments.clamav_binary'), '--no-summary', '--fdpass', $absolutePath]);

        return match ($result->exitCode()) {
            0 => FileScanStatus::Clean,
            1 => FileScanStatus::Infected,
            default => throw new RuntimeException('clamdscan failed: '.trim($result->errorOutput())),
        };
    }
}
