<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * POST /<resource>/import — one Excel workbook (multipart field `file`).
 */
final class ImportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:'.Config::integer('clinic.transfer.import_max_kb')],
        ];
    }
}
