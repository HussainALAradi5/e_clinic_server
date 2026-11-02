<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Enums\Identity\AuditAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Domain events that leave an audit_logs row (login, logout, status / password / permission changes).
 * Recorded by the RecordAuditableEvent listener.
 */
interface AuditableEvent
{
    public function auditAction(): AuditAction;

    /** Affected record. */
    public function auditSubject(): ?Model;

    /** Acting user id when it is not the authenticated user (e.g. the user who just signed in); null = Auth::id(). */
    public function auditActorId(): ?string;

    /**
     * Old and new values worth keeping (already non-sensitive; masking still applies).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function auditChanges(): array;
}
