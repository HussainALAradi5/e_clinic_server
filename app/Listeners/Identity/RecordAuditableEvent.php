<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Interfaces\Identity\AuditableEvent;
use App\Interfaces\Identity\AuditLogServiceInterface;

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
