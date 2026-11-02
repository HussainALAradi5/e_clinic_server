<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use App\Models\Appointments\MedicalRecord;
use Illuminate\Support\Arr;

/**
 * Notes and vitals sent (only the sent keys change) and, when `diagnoses` is sent, the full diagnosis list.
 */
final readonly class MedicalRecordData
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<array{icd10_code_id: string, is_primary: bool, notes: ?string}>|null  $diagnoses
     */
    private function __construct(public array $values, public ?array $diagnoses, public ?int $version) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = Arr::only($input, MedicalRecord::NOTES);
        foreach (Arr::only((array) ($input['vitals'] ?? []), MedicalRecord::VITALS) as $key => $value) {
            $values[$key] = $value === '' ? null : $value;
        }
        $diagnoses = array_key_exists('diagnoses', $input) ? array_values(array_map(fn (array $d): array => [
            'icd10_code_id' => (string) $d['icd10_code_id'],
            'is_primary' => (bool) ($d['is_primary'] ?? false),
            'notes' => isset($d['notes']) && $d['notes'] !== '' ? (string) $d['notes'] : null,
        ], (array) ($input['diagnoses'] ?? []))) : null;

        return new self($values, $diagnoses, isset($input['version']) ? (int) $input['version'] : null);
    }
}
