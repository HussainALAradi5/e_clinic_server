<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Http\Requests;

use App\Enums\Appointments\ConsentType;
use App\Modules\Appointments\Records\Data\ConsentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConsentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ConsentType::class)],
            'appointment_id' => ['nullable', 'uuid'],
            'treatment_id' => ['nullable', 'uuid'],
            'clinical_order_id' => ['nullable', 'uuid'],
            'description' => ['required', 'string', 'max:10000'],
            'signed_by_name' => ['required', 'string', 'max:150'],
            'signer_relationship' => ['required', Rule::in(['self', 'parent', 'guardian', 'spouse', 'child', 'other'])],
            'explained_by' => ['required', 'uuid', Rule::exists('employees', 'id')],
            'witness_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')],
            'signed_at' => ['required', 'date', 'before_or_equal:now'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'signature_attachment_id' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): ConsentData
    {
        return ConsentData::fromArray($this->validated());
    }
}
