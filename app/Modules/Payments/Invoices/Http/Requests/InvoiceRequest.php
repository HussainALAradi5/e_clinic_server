<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Http\Requests;

use App\Enums\Payments\DiscountType;
use App\Modules\Payments\Invoices\Data\InvoiceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Draft invoice lines: a price-list service (price from the list) or a free-text line with its own price.
 * On update, `items.*.id` keeps an existing line (and the visit / order it bills); lines left out are removed.
 */
final class InvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $create = $this->isMethod('POST');

        return [
            'version' => [$create ? 'prohibited' : 'required', 'integer'],
            'patient_id' => [$create ? 'required' : 'prohibited', 'uuid', Rule::exists('patients', 'id')->where('is_deleted', 0)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => [$create ? 'prohibited' : 'nullable', 'uuid'],
            'items.*.billable_service_id' => ['nullable', 'uuid', Rule::exists('billable_services', 'id')->where('is_deleted', 0)],
            'items.*.description' => ['nullable', 'required_without_all:items.*.billable_service_id,items.*.id', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'items.*.unit_price' => ['nullable', 'required_without_all:items.*.billable_service_id,items.*.id', 'decimal:0,3', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.doctor_id' => ['nullable', 'uuid', Rule::exists('doctors', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('items', []) as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $type = $item['discount_type'] ?? null;
                    $value = (float) ($item['discount_value'] ?? 0);
                    if ($type === DiscountType::Percentage->value && $value > 100) {
                        $validator->errors()->add("items.{$i}.discount_value", __('validation.max.numeric', ['attribute' => 'discount', 'max' => 100]));
                    }
                }
            },
        ];
    }

    public function toData(?string $patientId = null): InvoiceData
    {
        return InvoiceData::fromArray($this->validated(), $patientId);
    }
}
