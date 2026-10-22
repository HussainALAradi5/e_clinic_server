<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Imports;

use App\Enums\Reference\CompanyType;
use App\Modules\Reference\Companies\Contracts\CompanyServiceInterface;
use App\Modules\Reference\Companies\Data\CompanyData;
use App\Modules\Reference\Companies\Http\Requests\CompanyRequest;
use App\Shared\Transfer\Contracts\ImportDefinition;
use App\Shared\Transfer\ImportColumn;
use App\Shared\Transfer\ImportColumnType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Companies from Excel: same rules as POST /reference/companies, saved through CompanyService.
 */
final class CompanyImport implements ImportDefinition
{
    public function __construct(private readonly CompanyServiceInterface $companies) {}

    public function columns(): array
    {
        $types = [];
        foreach (CompanyType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        return [
            new ImportColumn('name', 'Name', required: true, example: 'Gulf Medical Supplies W.L.L.'),
            new ImportColumn('type', 'Type', required: true, example: CompanyType::Supplier->label(), type: ImportColumnType::Choice, options: $types),
            new ImportColumn('country_code', 'Country', required: true, example: 'BH', type: ImportColumnType::Country),
            new ImportColumn('contact_person', 'Contact person', example: 'Ali Hassan'),
            new ImportColumn('phone', 'Phone', example: '+973 1700 1234', help: 'International format is best.'),
            new ImportColumn('email', 'Email', example: 'orders@gulfmedical.bh'),
            new ImportColumn('website', 'Website', example: 'https://gulfmedical.bh', help: 'Full address starting with https://'),
            new ImportColumn('vat_number', 'VAT number', example: '200012345600002'),
            new ImportColumn('cr_number', 'CR number', example: '12345-1'),
            new ImportColumn('description', 'Description', example: 'Surgical consumables and gloves'),
            new ImportColumn('address.country_code', 'Address country', example: 'BH', help: 'Required when any address column is filled.', type: ImportColumnType::Country),
            new ImportColumn('address.building', 'Building', example: '2504'),
            new ImportColumn('address.road', 'Road', example: '2832'),
            new ImportColumn('address.block', 'Block', example: '428'),
            new ImportColumn('address.city', 'City', example: 'Seef'),
        ];
    }

    public function rules(): array
    {
        return CompanyRequest::create('/', 'POST')->rules();
    }

    public function import(array $row, Authenticatable $user): Model
    {
        return $this->companies->createCompany(CompanyData::fromArray($row));
    }
}
