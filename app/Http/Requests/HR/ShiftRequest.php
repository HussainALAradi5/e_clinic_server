<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\ShiftData;
use App\Enums\HR\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fixed: name, start, end. Flexible: also the arrive-by time (inside the window) and the hours due (which must fit).
 */
final class ShiftRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $flexible = $this->input('type') === ShiftType::Flexible->value;

        return [
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'type' => [$required, Rule::enum(ShiftType::class)],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i', 'after:start_time'],
            'latest_start' => [$flexible ? 'required' : 'nullable', 'date_format:H:i', 'after_or_equal:start_time', 'before:end_time'],
            'required_minutes' => [$flexible ? 'required' : 'nullable', 'integer', 'between:30,960', function (string $attribute, mixed $value, \Closure $fail) use ($flexible): void {
                if ($flexible && $this->filled(['start_time', 'end_time']) && (int) $value > $this->windowMinutes()) {
                    $fail(__('errors.SHIFT_HOURS_DO_NOT_FIT'));
                }
            }],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): ShiftData
    {
        return ShiftData::fromArray($this->validated());
    }

    private function windowMinutes(): int
    {
        $minutes = fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

        return $minutes($this->string('end_time')->toString()) - $minutes($this->string('start_time')->toString());
    }
}
