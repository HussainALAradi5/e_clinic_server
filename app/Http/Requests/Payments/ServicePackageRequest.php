<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Dtos\Payments\ServicePackageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A package's terms: one covered service, 2–100 sessions, a price above zero, valid 7–730 days after payment.
 */
final class ServicePackageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'covered_service_id' => ['required', 'uuid', Rule::exists('billable_services', 'id')->where('is_deleted', 0)->where('is_archived', 0)],
            'sessions' => ['required', 'integer', 'between:2,100'],
            'price' => ['required', 'decimal:0,3', 'gt:0', 'max:100000'],
            'validity_days' => ['required', 'integer', 'between:7,730'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): ServicePackageData
    {
        return new ServicePackageData(
            $this->string('name')->trim()->toString(),
            $this->input('description'),
            $this->string('covered_service_id')->toString(),
            $this->integer('sessions'),
            (string) $this->input('price'),
            $this->integer('validity_days'),
            $this->boolean('is_active', true),
        );
    }
}
