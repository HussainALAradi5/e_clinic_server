<?php

declare(strict_types=1);

namespace App\Console\Commands\Identity;

use App\Models\Identity\User;
use Illuminate\Console\Command;

/**
 * Exit code 0 when the database has no users yet (a new database, or a free Render database that was replaced), 1
 * otherwise. The container start script seeds on 0; this needs no shell (Tinker) or writable home directory.
 */
final class DatabaseEmpty extends Command
{
    protected $signature = 'clinic:database-empty';

    protected $description = 'Exit 0 when the database has no users (it needs seeding), 1 otherwise';

    public function handle(): int
    {
        $users = User::query()->count();
        $this->line("users: {$users}");

        return $users === 0 ? self::SUCCESS : self::FAILURE;
    }
}
