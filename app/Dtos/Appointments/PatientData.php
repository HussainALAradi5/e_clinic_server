<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use App\Dtos\Identity\PersonData;
use Illuminate\Support\Arr;

/**
 * Register (existing person by id, or a new person) or update medical / insurance data (only provided keys).
 */
final readonly class PatientData
{
    private const FIELDS = ['blood_group', 'allergies', 'chronic_conditions', 'family_history', 'surgical_history', 'emergency_contacts', 'insurance_company_id', 'insurance_policy_number', 'insurance_expiry_date', 'insurance_coverage_percent', 'notes'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(
        public array $values,
        public ?string $personId,
        public ?PersonData $person,
        public bool $forceCreate,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS));
        if (array_key_exists('emergency_contacts', $values)) {
            $values['emergency_contacts'] = EmergencyContactData::normalise($values['emergency_contacts']);
        }
        if (isset($values['insurance_coverage_percent'])) {
            $values['insurance_coverage_percent'] = (string) $values['insurance_coverage_percent'];
        }

        return new self(
            values: $values,
            personId: isset($input['person_id']) ? (string) $input['person_id'] : null,
            person: is_array($input['person'] ?? null) ? PersonData::fromArray($input['person']) : null,
            forceCreate: (bool) ($input['force_create'] ?? false),
        );
    }
}
