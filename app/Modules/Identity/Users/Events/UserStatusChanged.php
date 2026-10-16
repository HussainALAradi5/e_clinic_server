<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Events;

use App\Enums\Identity\AuditAction;
use App\Enums\Identity\UserStatus;
use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Account activated, deactivated, locked or unlocked (tokens already revoked when needed).
 */
final class UserStatusChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly UserStatus $from,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::StatusChanged;
    }

    public function auditSubject(): Model
    {
        return $this->user;
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditChanges(): array
    {
        return [['status' => $this->from->value], ['status' => $this->user->status->value, 'reason' => $this->reason]];
    }
}
