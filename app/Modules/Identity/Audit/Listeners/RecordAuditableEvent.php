<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Listeners;

use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Audit\Contracts\AuditLogServiceInterface;

/**
 * Writes the audit row of every AuditableEvent (login, logout, status, password and permission changes).
 */
final class RecordAuditableEvent
{
    public function __construct(private readonly AuditLogServiceInterface $audit) {}

    public function handle(AuditableEvent $event): void
    {
        [$old, $new] = $event->auditChanges();

        $this->audit->record($event->auditAction(), $event->auditSubject(), $old, $new, 'identity', $event->auditActorId());
    }
}
