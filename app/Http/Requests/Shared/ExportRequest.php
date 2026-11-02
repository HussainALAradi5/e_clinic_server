<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared;

use App\Dtos\Transfer\ExportData;
use App\Enums\Shared\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * POST /<resource>/export — `ids` (selected rows) or `filter` (the list on screen), never both.
 */
final class ExportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'ids' => ['nullable', 'prohibits:filter', 'array', 'min:1', 'max:'.Config::integer('clinic.bulk.max_ids')],
            'ids.*' => ['string', 'max:64'],
            'filter' => ['nullable', 'array'],
            'columns' => ['nullable', 'array', 'max:50'],
            'columns.*' => ['string', 'max:64'],
        ];
    }

    public function toData(): ExportData
    {
        return ExportData::fromArray($this->validated());
    }
}
