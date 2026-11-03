<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Makes a VAPID key pair for Web Push; put them in .env as WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY.
 * Changing the keys later makes every browser subscribe again.
 */
final class WebPushKeys extends Command
{
    protected $signature = 'webpush:keys';

    protected $description = 'Make a VAPID key pair for browser notifications (Web Push)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('WEBPUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
