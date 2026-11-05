<?php

declare(strict_types=1);

namespace App\Console\Commands\Identity;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * A one-time clean rebuild of a demo database: wipes every table and migrates again, so the start script seeds a
 * fresh demo. It runs once per token (RESET_DEMO=<any word>): the token is remembered after the reset, so a token
 * left in the environment never wipes the data again. Refused in production unless the demo is switched on.
 */
final class ResetDemo extends Command
{
    private const DONE_KEY = 'demo-reset-token';

    protected $signature = 'clinic:reset-demo {token : Any word; a new one resets again}';

    protected $description = 'Wipe and rebuild the demo database once per token (the start script then seeds it)';

    public function handle(): int
    {
        $token = trim((string) $this->argument('token'));
        if ($token === '' || $token === 'false') {
            return self::FAILURE;
        }
        if (app()->isProduction() && ! config('app.seed_demo')) {
            $this->error('Refused: a production database without the demo.');

            return self::FAILURE;
        }
        if (Cache::get(self::DONE_KEY) === $token) {
            $this->line("Demo reset {$token} already done.");

            return self::FAILURE;
        }

        $this->warn("Resetting the demo database ({$token}).");
        Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        Cache::forever(self::DONE_KEY, $token);

        return self::SUCCESS;
    }
}
