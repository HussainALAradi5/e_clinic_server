<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Http\Requests;

use App\Enums\HR\SalaryComponentType;
use App\Modules\HR\Employees\Data\SalaryComponentData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalaryComponentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SalaryComponentType::class)],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_taxable' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): SalaryComponentData
    {
        return new SalaryComponentData(
            type: SalaryComponentType::from($this->string('type')->toString()),
            name: $this->string('name')->toString(),
            amount: (string) $this->input('amount'),
            effectiveFrom: CarbonImmutable::parse($this->string('effective_from')->toString())->startOfDay(),
            effectiveTo: $this->filled('effective_to') ? CarbonImmutable::parse($this->string('effective_to')->toString())->startOfDay() : null,
            isTaxable: $this->boolean('is_taxable'),
        );
    }
}
