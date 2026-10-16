<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Data;

use App\Modules\Reference\Addresses\Data\AddressData;
use App\Shared\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Personal data of a person. Only keys present in `$provided` are applied on update (PATCH-like PUT).
 */
final readonly class PersonData
{
    /**
     * @param  list<string>  $provided  snake_case attribute names sent by the client
     * @param  list<string>|null  $notificationOptOut
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $firstNameAr = null,
        public ?string $lastNameAr = null,
        public ?Gender $gender = null,
        public ?CarbonImmutable $dateOfBirth = null,
        public ?string $nationalId = null,
        public ?string $nationalityCode = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $language = null,
        public ?array $notificationOptOut = null,
        public array $provided = [],
        public ?AddressData $address = null,
    ) {}

    /** `address` was sent (null = remove the address). */
    public function addressProvided(): bool
    {
        return in_array('address', $this->provided, true);
    }

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $string = fn (string $key): ?string => isset($input[$key]) && $input[$key] !== '' ? (string) $input[$key] : null;

        return new self(
            firstName: $string('first_name'),
            lastName: $string('last_name'),
            firstNameAr: $string('first_name_ar'),
            lastNameAr: $string('last_name_ar'),
            gender: isset($input['gender']) ? Gender::from((string) $input['gender']) : null,
            dateOfBirth: isset($input['date_of_birth']) ? CarbonImmutable::parse((string) $input['date_of_birth'])->startOfDay() : null,
            nationalId: $string('national_id'),
            nationalityCode: $string('nationality_code'),
            phone: $string('phone'),
            email: $string('email'),
            language: $string('language'),
            notificationOptOut: isset($input['notification_opt_out']) ? array_values(Arr::wrap($input['notification_opt_out'])) : null,
            provided: array_keys($input),
            address: is_array($input['address'] ?? null) ? AddressData::fromArray($input['address']) : null,
        );
    }

    /**
     * Model attributes for the provided keys.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $all = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'first_name_ar' => $this->firstNameAr,
            'last_name_ar' => $this->lastNameAr,
            'gender' => $this->gender,
            'date_of_birth' => $this->dateOfBirth,
            'national_id' => $this->nationalId,
            'nationality_code' => $this->nationalityCode,
            'phone' => $this->phone,
            'email' => $this->email,
            'language' => $this->language,
            'notification_opt_out' => $this->notificationOptOut ?? [],
        ];

        return $this->provided === [] ? array_filter($all, fn (mixed $v): bool => $v !== null) : Arr::only($all, array_diff($this->provided, ['address']));
    }
}
