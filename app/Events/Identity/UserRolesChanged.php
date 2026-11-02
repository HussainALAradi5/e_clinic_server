<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Enums\Identity\AuditAction;
use App\Interfaces\Identity\AuditableEvent;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user's roles were replaced; the user's tokens are revoked so new rights apply at next sign-in.
 */
final class UserRolesChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<string>  $from
     * @param  list<string>  $to
     */
    public function __construct(
        public readonly User $user,
        public readonly array $from,
        public readonly array $to,
    ) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::PermissionChanged;
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
        return [['roles' => $this->from], ['roles' => $this->to]];
    }
}
