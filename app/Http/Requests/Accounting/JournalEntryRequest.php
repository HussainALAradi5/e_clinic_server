<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Dtos\Accounting\JournalEntryData;
use App\Enums\Accounting\JournalEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JournalEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'type' => ['required', Rule::in([JournalEntryType::Manual->value, JournalEntryType::Adjustment->value])],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2', 'max:200'],
            'lines.*.account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where('is_deleted', 0)],
            'lines.*.debit' => ['nullable', 'required_without:lines.*.credit', 'decimal:0,3', 'min:0', 'max:999999999'],
            'lines.*.credit' => ['nullable', 'required_without:lines.*.debit', 'decimal:0,3', 'min:0', 'max:999999999'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'lines.*.patient_id' => ['nullable', 'uuid', Rule::exists('patients', 'id')],
            'lines.*.doctor_id' => ['nullable', 'uuid', Rule::exists('doctors', 'id')],
        ];
    }

    public function toData(): JournalEntryData
    {
        return JournalEntryData::fromArray($this->validated());
    }
}
