<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\TerminationData;
use App\Enums\HR\TerminationReason;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TerminateEmployeeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'termination_date' => ['required', 'date'],
            'reason' => ['required', Rule::enum(TerminationReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(): TerminationData
    {
        return new TerminationData(
            CarbonImmutable::parse($this->string('termination_date')->toString())->startOfDay(),
            TerminationReason::from($this->string('reason')->toString()),
            $this->filled('notes') ? $this->string('notes')->toString() : null,
        );
    }
}
