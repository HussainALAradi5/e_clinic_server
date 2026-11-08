<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One line of a record's event log: who, when, what happened and what changed. No IP address or device.
 *
 * @mixin AuditLog
 */
final class ActivityEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'entity' => $this->auditableAlias() ?? $this->auditable_type,
            'changes' => $this->changes(),
            'changed_by' => $this->user?->person->full_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return list<array{field: string, label: string, old: mixed, new: mixed}>
     */
    private function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_map(
            fn (string $field): array => [
                'field' => $field,
                'label' => Str::ucfirst(str_replace('_', ' ', $field)),
                'old' => $this->display($old[$field] ?? null),
                'new' => $this->display($new[$field] ?? null),
            ],
            array_values(array_unique([...array_keys($old), ...array_keys($new)])),
        );
    }

    /** A stored date (2026-01-02 00:00:00) reads as the date alone. */
    private function display(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/^(\d{4}-\d{2}-\d{2}) 00:00:00$/', '$1', $value) : $value;
    }
}
