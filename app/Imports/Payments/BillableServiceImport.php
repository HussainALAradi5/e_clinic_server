<?php

declare(strict_types=1);

namespace App\Imports\Payments;

use App\Dtos\Payments\BillableServiceData;
use App\Dtos\Transfer\ImportColumn;
use App\Enums\Appointments\ConsentType;
use App\Enums\Payments\ServiceCategory;
use App\Enums\Transfer\ImportColumnType;
use App\Http\Requests\Payments\BillableServiceRequest;
use App\Interfaces\Payments\BillableServiceServiceInterface;
use App\Interfaces\Transfer\ImportDefinition;
use App\Models\Accounting\Account;
use App\Models\Appointments\Specialty;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Price list from Excel: accounts and specialties by their codes (what billing staff see), saved through the service.
 */
final class BillableServiceImport implements ImportDefinition
{
    public function __construct(private readonly BillableServiceServiceInterface $services) {}

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', required: true, example: 'CONS-GP', help: 'Letters, digits, - and _ only. Must be new.'),
            new ImportColumn('name', 'Name', required: true, example: 'General practice consultation'),
            new ImportColumn('name_ar', 'Arabic name', example: 'استشارة طب عام'),
            new ImportColumn('category', 'Category', required: true, example: ServiceCategory::Consultation->label(), type: ImportColumnType::Choice, options: self::options(ServiceCategory::cases())),
            new ImportColumn('price', 'Price (BHD)', required: true, example: '15.000', help: 'Up to 3 decimals.'),
            new ImportColumn('tax_rate', 'VAT %', required: true, example: '0', help: 'Bahrain VAT is 10; many medical services are zero-rated.'),
            new ImportColumn('revenue_account', 'Revenue account code', required: true, example: '4100', help: 'A revenue account from the chart of accounts.'),
            new ImportColumn('specialty', 'Specialty code', example: 'GP'),
            new ImportColumn('is_insurable', 'Covered by insurance', example: 'Yes', type: ImportColumnType::Choice, options: ['yes' => 'Yes', 'no' => 'No']),
            new ImportColumn('consent_type', 'Consent required', example: '', type: ImportColumnType::Choice, options: self::options(ConsentType::cases())),
        ];
    }

    public function rules(): array
    {
        return [
            ...Arr::except(BillableServiceRequest::create('/', 'POST')->rules(), ['revenue_account_id', 'specialty_id']),
            'revenue_account' => ['required', 'string', 'max:20'],
            'specialty' => ['nullable', 'string', 'max:20'],
            'is_insurable' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        $account = Account::query()->where('code', $row['revenue_account'])->value('id');
        $specialty = filled($row['specialty'] ?? null) ? Specialty::query()->where('code', $row['specialty'])->value('id') : null;
        if ($account === null || (filled($row['specialty'] ?? null) && $specialty === null)) {
            throw ValidationException::withMessages([$account === null ? 'revenue_account' : 'specialty' => [__('validation.exists', ['attribute' => 'code'])]]);
        }

        return $this->services->createService(BillableServiceData::fromArray([
            ...Arr::except($row, ['revenue_account', 'specialty']),
            'is_insurable' => ($row['is_insurable'] ?? 'yes') === 'yes',
            'revenue_account_id' => $account,
            'specialty_id' => $specialty,
        ]));
    }

    /**
     * @param  list<ServiceCategory|ConsentType>  $cases
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
