<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Jobs;

use App\Modules\Identity\Audit\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Inserts one prepared audit row (request context captured at dispatch time). Idempotent by id.
 */
final class WriteAuditLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public readonly array $attributes) {}

    public function handle(): void
    {
        if (! AuditLog::query()->whereKey($this->attributes['id'])->exists()) {
            (new AuditLog)->forceFill($this->attributes)->save();
        }
    }
}
