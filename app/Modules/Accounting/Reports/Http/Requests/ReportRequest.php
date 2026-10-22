<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Reports\Http\Requests;

use App\Enums\Accounting\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parameters of one report (`type` from the route default or the export body).
 */
final class ReportRequest extends FormRequest
{
    public function type(): ReportType
    {
        return ReportType::from((string) ($this->route('report') ?? $this->input('type')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $export = $this->route('report') === null;
        $prefix = $export ? 'params.' : '';
        $type = ReportType::tryFrom((string) ($this->route('report') ?? $this->input('type')));

        $rules = match ($type) {
            ReportType::TrialBalance => ['as_of' => ['required', 'date'], 'include_zero' => ['boolean']],
            ReportType::BalanceSheet, ReportType::ReceivablesAging => ['as_of' => ['required', 'date']],
            ReportType::GeneralLedger => ['account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')], ...self::range($prefix)],
            ReportType::IncomeStatement => [
                ...self::range($prefix),
                'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
                'compare_from' => ['nullable', 'date', 'required_with:compare_to'],
                'compare_to' => ['nullable', 'date', 'after_or_equal:'.$prefix.'compare_from'],
            ],
            ReportType::CashFlow, ReportType::RevenueByDoctor, ReportType::RevenueByService => self::range($prefix),
            null => [],
        };

        $prefixed = [];
        foreach ($rules as $field => $fieldRules) {
            $prefixed[$prefix.$field] = $fieldRules;
        }

        return $export
            ? ['type' => ['required', Rule::enum(ReportType::class)], 'format' => ['required', Rule::in(['pdf', 'xlsx'])], 'params' => ['required', 'array'], ...$prefixed]
            : $prefixed;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        $validated = $this->validated();

        return $this->route('report') === null ? (array) ($validated['params'] ?? []) : $validated;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function range(string $prefix): array
    {
        return ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:'.$prefix.'from']];
    }
}
