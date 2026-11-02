<?php

declare(strict_types=1);

namespace App\Dtos\Bulk;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One bulk action of a resource: the permission expression behind it and the per-record handler.
 */
final readonly class BulkAction
{
    /**
     * @param  Closure(Model, ?string): mixed  $handler
     */
    public function __construct(
        public string $name,
        public string $permission,
        public Closure $handler,
        public bool $needsReason = false,
        public bool $targetsDeleted = false,
        public ?string $inverse = null,
    ) {}
}
