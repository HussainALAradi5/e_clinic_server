<?php

declare(strict_types=1);

namespace App\Imports\Identity;

use App\Dtos\Identity\PersonData;
use App\Dtos\Transfer\ImportColumn;
use App\Enums\Shared\Gender;
use App\Enums\Transfer\ImportColumnType;
use App\Interfaces\Identity\UserServiceInterface;
use App\Interfaces\Transfer\ImportDefinition;
use App\Models\Identity\Person;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * People (patients or staff records without an account) from Excel. A row matching an existing person
 * (same national ID, or same mobile and date of birth) is rejected instead of creating a duplicate.
 */
final class PersonImport implements ImportDefinition
{
    public function __construct(private readonly UserServiceInterface $users) {}

    public function columns(): array
    {
        $genders = [];
        foreach (Gender::cases() as $gender) {
            $genders[$gender->value] = $gender->label();
        }

        return [
            new ImportColumn('first_name', 'First name', required: true, example: 'Fatima'),
            new ImportColumn('last_name', 'Last name', required: true, example: 'Al Aradi'),
            new ImportColumn('gender', 'Gender', required: true, example: Gender::Female->label(), type: ImportColumnType::Choice, options: $genders),
            new ImportColumn('date_of_birth', 'Date of birth', required: true, example: '1988-04-21', type: ImportColumnType::Date),
            new ImportColumn('phone', 'Mobile', required: true, example: '+973 3600 1234', help: 'Bahrain numbers may omit +973.'),
            new ImportColumn('email', 'Email', example: 'fatima@example.com'),
            new ImportColumn('national_id', 'National ID (CPR)', example: '880421234', help: 'Used to detect people already registered.'),
            new ImportColumn('nationality_code', 'Nationality', example: 'BH', type: ImportColumnType::Country),
            new ImportColumn('address.country_code', 'Address country', example: 'BH', help: 'Required when any address column is filled.', type: ImportColumnType::Country),
            new ImportColumn('address.building', 'Building', example: '1205'),
            new ImportColumn('address.road', 'Road', example: '4031'),
            new ImportColumn('address.block', 'Block', example: '540'),
            new ImportColumn('address.city', 'City', example: 'Saar'),
        ];
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'nationality_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')->where('is_active', true)],
            'address' => ['nullable', 'array'],
            'address.country_code' => ['required_with:address', 'string', 'size:2'],
            'address.building' => ['nullable', 'string', 'max:20'],
            'address.road' => ['nullable', 'string', 'max:20'],
            'address.block' => ['nullable', 'string', 'max:10'],
            'address.city' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        $data = PersonData::fromArray($row);

        $existing = $this->users->findDuplicates($data)->first();
        if ($existing instanceof Person) {
            throw ValidationException::withMessages([
                filled($data->nationalId) ? 'national_id' : 'phone' => __('errors.PERSON_ALREADY_REGISTERED', ['code' => $existing->code]),
            ]);
        }

        return $this->users->findOrCreatePerson($data);
    }
}
