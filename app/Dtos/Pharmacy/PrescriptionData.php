<?php

declare(strict_types=1);

namespace App\Dtos\Pharmacy;

use Carbon\CarbonImmutable;

/**
 * Prescription form. The doctor is the signed-in doctor (others name one); the overrides confirm a duplicate-therapy
 * or allergy warning the prescriber has seen.
 */
final readonly class PrescriptionData
{
    /**
     * @param  list<PrescriptionItemData>  $items
     */
    public function __construct(
        public string $patientId,
        public array $items,
        public ?string $doctorId = null,
        public ?string $appointmentId = null,
        public ?string $treatmentId = null,
        public ?CarbonImmutable $validUntil = null,
        public ?string $notes = null,
        public bool $overrideDuplicate = false,
        public bool $overrideAllergy = false,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        /** @var list<array<string, mixed>> $items */
        $items = $input['items'];

        return new self(
            (string) $input['patient_id'],
            array_map(PrescriptionItemData::fromArray(...), $items),
            self::nullable($input, 'doctor_id'),
            self::nullable($input, 'appointment_id'),
            self::nullable($input, 'treatment_id'),
            isset($input['valid_until']) ? CarbonImmutable::parse((string) $input['valid_until'])->startOfDay() : null,
            self::nullable($input, 'notes'),
            (bool) ($input['override_duplicate'] ?? false),
            (bool) ($input['override_allergy'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function nullable(array $input, string $key): ?string
    {
        return isset($input[$key]) && $input[$key] !== '' ? (string) $input[$key] : null;
    }
}
