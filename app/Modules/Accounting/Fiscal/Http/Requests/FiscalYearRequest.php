<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Http\Requests;

use App\Modules\Accounting\Fiscal\Data\FiscalYearData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FiscalYearRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $start = strtotime((string) $this->input('start_date'));
        $max = $start === false ? [] : ['before:'.date('Y-m-d', strtotime('+18 months', $start))];

        return [
            'name' => ['required', 'string', 'max:20', Rule::unique('fiscal_years', 'name')->where('is_deleted', 0)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date', ...$max],
            'with_adjustment_period' => ['boolean'],
        ];
    }

    public function toData(): FiscalYearData
    {
        return FiscalYearData::fromArray($this->validated());
    }
}
