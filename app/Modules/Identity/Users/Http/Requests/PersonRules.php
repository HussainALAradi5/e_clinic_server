<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Requests;

use App\Modules\Reference\Addresses\Data\AddressData;
use App\Shared\Enums\Gender;
use Illuminate\Validation\Rule;

/**
 * Validation of a new person nested in another record (`person` of POST /patients and POST /hr/employees),
 * so registration screens send personal data once and the server reuses or creates the person.
 */
final class PersonRules
{
    /**
     * @param  string  $prefix  e.g. `person`
     * @param  bool  $staff  staff need a national ID and must be at least 16
     * @return array<string, mixed>
     */
    public static function nested(string $prefix, bool $staff = false): array
    {
        $p = $prefix.'.';
        $required = 'required_without:'.$prefix.'_id';

        return [
            $prefix => [$required, 'nullable', 'array'],
            $p.'first_name' => ["required_with:{$prefix}", 'string', 'max:100'],
            $p.'last_name' => ["required_with:{$prefix}", 'string', 'max:100'],
            $p.'first_name_ar' => ['nullable', 'string', 'max:100'],
            $p.'last_name_ar' => ['nullable', 'string', 'max:100'],
            $p.'gender' => ["required_with:{$prefix}", Rule::enum(Gender::class)],
            $p.'date_of_birth' => ["required_with:{$prefix}", 'date', $staff ? 'before_or_equal:-16 years' : 'before_or_equal:today'],
            $p.'national_id' => [$staff ? "required_with:{$prefix}" : 'nullable', 'string', 'max:30'],
            $p.'nationality_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')->where('is_active', true)],
            $p.'phone' => ["required_with:{$prefix}", 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            $p.'email' => ['nullable', 'email:rfc', 'max:255'],
            ...AddressData::rules($p.'address'),
        ];
    }
}
