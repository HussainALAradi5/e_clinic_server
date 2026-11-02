<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'module' => $this->module,
            'auditable_type' => $this->auditableAlias() ?? $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_id' => $this->request_id,
            'created_at' => $this->created_at->toIso8601String(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'full_name' => $this->user->person->full_name,
                'email' => $this->user->person->email,
            ]),
        ];
    }
}
