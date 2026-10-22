<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Imports;

use App\Enums\Pharmacy\DispenseUnit;
use App\Enums\Pharmacy\MedicationForm;
use App\Enums\Reference\CompanyType;
use App\Modules\Pharmacy\Medications\Contracts\MedicationServiceInterface;
use App\Modules\Pharmacy\Medications\Data\MedicationData;
use App\Modules\Pharmacy\Medications\Http\Requests\MedicationRequest;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Transfer\Contracts\ImportDefinition;
use App\Shared\Transfer\ImportColumn;
use App\Shared\Transfer\ImportColumnType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Medication catalogue from Excel (opening a pharmacy): manufacturer by company code, saved through the service so
 * each row also gets its price-list service.
 */
final class MedicationImport implements ImportDefinition
{
    public function __construct(private readonly MedicationServiceInterface $medications) {}

    public function columns(): array
    {
        $yesNo = ['yes' => 'Yes', 'no' => 'No'];

        return [
            new ImportColumn('code', 'Code', required: true, example: 'MED-AMOX-500', help: 'Letters, digits, - and _ only. Also the price-list code.'),
            new ImportColumn('generic_name', 'Generic name', required: true, example: 'Amoxicillin'),
            new ImportColumn('brand_name', 'Brand name', example: 'Amoxil'),
            new ImportColumn('name_ar', 'Arabic name', example: 'أموكسيسيلين'),
            new ImportColumn('form', 'Form', required: true, example: MedicationForm::Capsule->label(), type: ImportColumnType::Choice, options: self::options(MedicationForm::cases())),
            new ImportColumn('strength', 'Strength', required: true, example: '500 mg'),
            new ImportColumn('unit', 'Unit', required: true, example: DispenseUnit::Capsule->label(), type: ImportColumnType::Choice, options: self::options(DispenseUnit::cases())),
            new ImportColumn('gtin', 'Barcode (GTIN)', example: '05000123456789', help: '8 to 14 digits.'),
            new ImportColumn('manufacturer', 'Manufacturer code', example: 'CMP-000001', help: 'Code of a company of type manufacturer.'),
            new ImportColumn('origin_country_code', 'Origin country', example: 'GB', help: 'Two-letter country code.'),
            new ImportColumn('price', 'Price (BHD)', required: true, example: '2.500', help: 'Up to 3 decimals, per unit.'),
            new ImportColumn('tax_rate', 'VAT %', required: true, example: '0'),
            new ImportColumn('requires_prescription', 'Prescription only', example: 'Yes', type: ImportColumnType::Choice, options: $yesNo),
            new ImportColumn('is_controlled', 'Controlled drug', example: 'No', type: ImportColumnType::Choice, options: $yesNo),
            new ImportColumn('reorder_level', 'Reorder level', example: '50'),
            new ImportColumn('storage_notes', 'Storage', example: 'Below 25 °C'),
        ];
    }

    public function rules(): array
    {
        return [
            ...Arr::except(MedicationRequest::create('/', 'POST')->rules(), ['manufacturer_id', 'requires_prescription', 'is_controlled']),
            'manufacturer' => ['nullable', 'string', 'max:30'],
            'requires_prescription' => ['nullable', Rule::in(['yes', 'no'])],
            'is_controlled' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        $manufacturer = null;
        if (filled($row['manufacturer'] ?? null)) {
            $manufacturer = Company::query()->where('code', $row['manufacturer'])->where('type', CompanyType::Manufacturer->value)->value('id');
            if ($manufacturer === null) {
                throw ValidationException::withMessages(['manufacturer' => [__('validation.exists', ['attribute' => 'manufacturer'])]]);
            }
        }

        return $this->medications->create(MedicationData::fromArray([
            ...Arr::except($row, ['manufacturer']),
            'manufacturer_id' => $manufacturer,
            'requires_prescription' => ($row['requires_prescription'] ?? 'yes') !== 'no',
            'is_controlled' => ($row['is_controlled'] ?? 'no') === 'yes',
        ]));
    }

    /**
     * @param  list<MedicationForm|DispenseUnit>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
