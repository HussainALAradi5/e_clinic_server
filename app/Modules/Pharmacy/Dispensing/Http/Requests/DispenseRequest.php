<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Http\Requests;

use App\Enums\Pharmacy\MedicationRoute;
use App\Modules\Pharmacy\Dispensing\Data\AdministerData;
use App\Modules\Pharmacy\Dispensing\Data\DispenseData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pharmacy hand-over (POST /pharmacy/dispensations) and medicines given in a session
 * (POST /appointments/{appointment}/medications — no patient / prescription, route per item).
 */
final class DispenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $session = $this->route('appointment') !== null;

        return [
            'patient_id' => $session ? ['prohibited'] : ['required', 'uuid', Rule::exists('patients', 'id')],
            'prescription_id' => $session ? ['prohibited'] : ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.medication_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:100000'],
            'items.*.prescription_item_id' => $session ? ['prohibited'] : ['nullable', 'uuid'],
            'items.*.batch_id' => ['nullable', 'uuid'],
            'items.*.route' => $session ? ['required', Rule::enum(MedicationRoute::class)] : ['prohibited'],
            'items.*.dose' => $session ? ['nullable', 'string', 'max:50'] : ['prohibited'],
            'witness_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toDispenseData(): DispenseData
    {
        return DispenseData::fromArray($this->validated());
    }

    public function toAdministerData(): AdministerData
    {
        return AdministerData::fromArray($this->validated());
    }
}
