<?php

declare(strict_types=1);

namespace App\Events\Appointments;

use App\Enums\Identity\AuditAction;
use App\Interfaces\Identity\AuditableEvent;
use App\Models\Appointments\Patient;
use App\Models\Appointments\PatientAccessGrant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/** A clinician broke the glass on a patient's file: audited, and the admins are told. */
final class EmergencyAccessGranted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Patient $patient, public readonly PatientAccessGrant $grant) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::EmergencyAccess;
    }

    public function auditSubject(): Model
    {
        return $this->patient;
    }

    public function auditActorId(): string
    {
        return $this->grant->user_id;
    }

    public function auditChanges(): array
    {
        return [[], ['reason' => $this->grant->reason, 'until' => $this->grant->expires_at->toIso8601String()]];
    }
}
