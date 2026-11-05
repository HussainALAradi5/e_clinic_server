<?php

declare(strict_types=1);

namespace App\Console\Commands\Identity;

use App\Models\Identity\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Exit code 0 when the database should be seeded: it has no users yet, or the demo is wanted (outside production,
 * or SEED_DEMO=true) and no demo account exists — e.g. a free Render database left with only the super admin.
 * 1 otherwise. The container start script seeds on 0 (every seeder skips what already exists).
 */
final class NeedsSeed extends Command
{
    protected $signature = 'clinic:needs-seed';

    protected $description = 'Exit 0 when the database needs seeding (no users, or no demo accounts while the demo is wanted)';

    public function handle(): int
    {
        $users = User::query()->count();
        $demo = User::query()->whereHas('person', fn ($q) => $q->where('email', 'like', '%'.DemoSeeder::EMAIL_DOMAIN))->count();
        $demoWanted = ! app()->isProduction() || (bool) config('app.seed_demo');
        $this->line("users: {$users}, demo accounts: {$demo}");

        return $users === 0 || ($demoWanted && $demo === 0) ? self::SUCCESS : self::FAILURE;
    }
}
