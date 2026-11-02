<?php

declare(strict_types=1);

namespace App\Imports\Appointments;

use App\Dtos\Appointments\PatientData;
use App\Dtos\Transfer\ImportColumn;
use App\Enums\Reference\CompanyType;
use App\Enums\Transfer\ImportColumnType;
use App\Imports\Identity\PersonImport;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Interfaces\Transfer\ImportDefinition;
use App\Models\Reference\Company;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Patient files from Excel (e.g. moving from another system): the person columns of PersonImport plus emergency
 * contact and insurance. A person already on file is registered as a patient, never duplicated.
 */
final class PatientImport implements ImportDefinition
{
    private const PATIENT_FIELDS = ['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation', 'insurer', 'insurance_policy_number', 'insurance_expiry_date', 'insurance_coverage_percent'];

    public function __construct(
        private readonly PatientServiceInterface $patients,
        private readonly PersonImport $people,
    ) {}

    public function columns(): array
    {
        return [
            ...$this->people->columns(),
            new ImportColumn('emergency_contact_name', 'Emergency contact', example: 'Ali Al Khalifa'),
            new ImportColumn('emergency_contact_phone', 'Emergency phone', example: '+973 3900 1122'),
            new ImportColumn('emergency_contact_relation', 'Relation', example: 'Spouse'),
            new ImportColumn('insurer', 'Insurer', example: 'Gulf Insurance Group', help: 'Name of an insurer from Companies.'),
            new ImportColumn('insurance_policy_number', 'Policy number', example: 'GIG-554120'),
            new ImportColumn('insurance_expiry_date', 'Insurance expiry', example: '2027-06-30', type: ImportColumnType::Date),
            new ImportColumn('insurance_coverage_percent', 'Coverage %', example: '80'),
        ];
    }

    public function rules(): array
    {
        return [
            ...$this->people->rules(),
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'insurer' => ['nullable', 'string', 'max:200'],
            'insurance_policy_number' => ['nullable', 'string', 'max:100'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'insurance_coverage_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        $insurerId = null;
        if (filled($row['insurer'] ?? null)) {
            $insurerId = Company::query()->where('type', CompanyType::Insurer)->whereRaw('lower(name) = ?', [mb_strtolower((string) $row['insurer'])])->value('id');
            if ($insurerId === null) {
                throw ValidationException::withMessages(['insurer' => [__('validation.exists', ['attribute' => 'insurer'])]]);
            }
        }

        return $this->patients->create(PatientData::fromArray([
            ...Arr::only($row, self::PATIENT_FIELDS),
            'insurance_company_id' => $insurerId,
            'person' => Arr::except($row, self::PATIENT_FIELDS),
        ]));
    }
}
