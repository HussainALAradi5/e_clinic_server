<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Enums\Identity\AuditAction;
use App\Enums\Identity\LoginFailureReason;
use App\Interfaces\Identity\AuditableEvent;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Failed sign-in (also stored in login_attempts).
 */
final class LoginFailed implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly string $email, public readonly LoginFailureReason $reason, public readonly ?User $user = null) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::LoginFailed;
    }

    public function auditSubject(): ?Model
    {
        return $this->user;
    }

    public function auditActorId(): ?string
    {
        return $this->user?->id;
    }

    public function auditChanges(): array
    {
        return [[], ['email' => $this->email, 'reason' => $this->reason->value]];
    }
}
