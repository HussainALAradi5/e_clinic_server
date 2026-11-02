<?php

declare(strict_types=1);

namespace App\Enums\Shared;

/**
 * Model lifecycle events recorded by the Auditable trait. Values match the Identity AuditAction enum.
 */
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
}
