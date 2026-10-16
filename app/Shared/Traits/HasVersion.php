<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Exceptions\ConcurrencyConflictException;
use Illuminate\Database\Eloquent\Model;

/**
 * Optimistic locking via the `version` column: starts at 1, incremented on every update.
 * Clients send the version they read; services call assertVersion() before changing the record.
 *
 * @mixin Model
 */
trait HasVersion
{
    public static function bootHasVersion(): void
    {
        static::creating(fn (self $model) => $model->setAttribute('version', 1));
        static::updating(fn (self $model) => $model->setAttribute('version', (int) $model->getOriginal('version') + 1));
    }

    public function initializeHasVersion(): void
    {
        $this->mergeCasts(['version' => 'integer']);
    }

    public function assertVersion(int $expected): void
    {
        if ((int) $this->getAttribute('version') !== $expected) {
            throw new ConcurrencyConflictException(context: ['current_version' => (int) $this->getAttribute('version')]);
        }
    }
}
