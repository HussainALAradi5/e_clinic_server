<?php

declare(strict_types=1);

namespace App\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

final class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Permissions are checked per action by BulkActionService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:50'],
            'ids' => ['required_without:filter', 'prohibits:filter', 'array', 'min:1', 'max:'.Config::integer('clinic.bulk.max_ids')],
            'ids.*' => ['string', 'max:64'],
            'filter' => ['required_without:ids', 'array'],
            'reason' => ['nullable', 'string', 'max:500'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
