<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Events;

use App\Enums\Identity\AuditAction;
use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Token(s) revoked by the user (logout or logout from all devices).
 */
final class UserLoggedOut implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly int $tokensRevoked = 1, public readonly bool $allDevices = false) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::Logout;
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
        return [[], ['tokens_revoked' => $this->tokensRevoked]];
    }
}
