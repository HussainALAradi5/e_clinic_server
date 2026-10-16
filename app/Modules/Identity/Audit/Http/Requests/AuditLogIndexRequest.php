<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Http\Requests;

use App\Enums\Identity\AuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The audit list always needs a date range (`date_from` + `date_to`, `from` + `to`, or a `date_range` preset);
 * the 93-day limit is enforced by the list query. Plain `user_id` / `module` / `action` are accepted as shortcuts.
 */
final class AuditLogIndexRequest extends FormRequest
{
    private const SHORTCUTS = ['user_id', 'module', 'action'];

    protected function prepareForValidation(): void
    {
        $merge = array_filter([
            'date_from' => $this->query('date_from', $this->query('from')),
            'date_to' => $this->query('date_to', $this->query('to')),
        ]);

        $filter = (array) $this->query('filter', []);
        foreach (self::SHORTCUTS as $field) {
            if ($this->filled($field) && ! isset($filter[$field])) {
                $filter[$field] = ['eq' => $this->query($field)];
            }
        }

        $this->query->add([...$merge, 'filter' => $filter]);
        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_range' => ['required_without_all:date_from,date_to', 'nullable', 'string'],
            'date_from' => ['required_without:date_range', 'nullable', 'date'],
            'date_to' => ['required_without:date_range', 'nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'uuid'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
