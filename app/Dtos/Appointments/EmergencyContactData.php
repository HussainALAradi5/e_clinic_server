<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

/**
 * A patient's emergency / additional contacts: up to three, all optional; each has a phone, the name and how they
 * are related are optional. Stored as an encrypted list on the patient.
 */
final readonly class EmergencyContactData
{
    public const MAX = 3;

    /**
     * Validation of the `emergency_contacts` list (`[]` or null clears it).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'emergency_contacts'): array
    {
        return [
            $prefix => ['nullable', 'array', 'max:'.self::MAX],
            $prefix.'.*.name' => ['nullable', 'string', 'max:150'],
            $prefix.'.*.relation' => ['nullable', 'string', 'max:50'],
            $prefix.'.*.phone' => ['required', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
        ];
    }

    /**
     * The validated list as stored: `[{name, relation, phone}]`, blanks as null, at most three.
     *
     * @param  mixed  $input  validated `emergency_contacts`
     * @return list<array{name: ?string, relation: ?string, phone: string}>
     */
    public static function normalise(mixed $input): array
    {
        $contacts = [];
        foreach (is_array($input) ? array_slice($input, 0, self::MAX) : [] as $contact) {
            $contacts[] = [
                'name' => self::text($contact['name'] ?? null),
                'relation' => self::text($contact['relation'] ?? null),
                'phone' => trim((string) ($contact['phone'] ?? '')),
            ];
        }

        return $contacts;
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
