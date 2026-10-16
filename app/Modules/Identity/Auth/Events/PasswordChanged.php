<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Events;

use App\Enums\Identity\AuditAction;
use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The user changed their password (other sessions revoked).
 */
final class PasswordChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::PasswordChanged;
    }

    public function auditSubject(): Model
    {
        return $this->user;
    }

    public function auditActorId(): string
    {
        return $this->user->id;
    }

    public function auditChanges(): array
    {
        return [[], ['via' => 'change']];
    }
}
