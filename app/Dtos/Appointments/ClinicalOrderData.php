<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Illuminate\Support\Arr;

final readonly class ClinicalOrderData
{
    public const FIELDS = ['type', 'patient_id', 'ordered_by', 'appointment_id', 'treatment_id', 'billable_service_id', 'referred_to_doctor_id', 'referred_to_specialty_id', 'referred_to_company_id', 'body_part', 'clinical_notes', 'priority'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)));
    }
}
