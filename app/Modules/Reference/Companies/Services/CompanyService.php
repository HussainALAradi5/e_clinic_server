<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Services;

use App\Modules\Reference\Addresses\Contracts\AddressServiceInterface;
use App\Modules\Reference\Companies\Contracts\CompanyServiceInterface;
use App\Modules\Reference\Companies\Data\CompanyData;
use App\Modules\Reference\Companies\Exceptions\CompanyInUseException;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Reference\Countries\Contracts\CountryServiceInterface;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<Company>
 */
final class CompanyService extends BaseEntityService implements CompanyServiceInterface
{
    public function __construct(
        ApplyListQuery $listQuery,
        private readonly AddressServiceInterface $addresses,
        private readonly CountryServiceInterface $countries,
    ) {
        parent::__construct($listQuery);
    }

    public function listCompanies(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findCompany(string $id): Company
    {
        return $this->find($id);
    }

    public function findWithDeletedCompany(string $id): Company
    {
        return $this->findWithDeleted($id);
    }

    public function usage(Company $company): array
    {
        $counts = [];
        foreach ((array) Config::get('reference.company_usages', []) as $key => $usage) {
            /** @var array{table: string, column: string} $usage */
            if (Schema::hasTable($usage['table'])) {
                $counts[(string) $key] = DB::table($usage['table'])->where($usage['column'], $company->id)->count();
            }
        }

        return $counts;
    }

    public function createCompany(CompanyData $data): Company
    {
        $this->assertCountry($data);

        return DB::transaction(function () use ($data): Company {
            $company = new Company($data->values);
            if ($data->address !== null) {
                $company->address_id = $this->addresses->save(null, $data->address)->id;
            }
            $company->save();

            return $this->findCompany($company->id);
        });
    }

    public function updateCompany(Company $company, CompanyData $data): Company
    {
        $this->assertCountry($data);

        return DB::transaction(function () use ($company, $data): Company {
            $company->fill($data->values);

            if ($data->addressProvided) {
                if ($data->address === null) {
                    $old = $company->address;
                    $company->address_id = null;
                    $company->save();
                    $this->addresses->remove($old);
                } else {
                    $company->address_id = $this->addresses->save($company->address, $data->address)->id;
                }
            }
            $company->save();

            return $this->findCompany($company->id);
        });
    }

    public function deleteCompany(Company $company): void
    {
        $this->delete($company);
    }

    public function archiveCompany(Company $company): Company
    {
        return $company->isArchived() ? $company : $this->archive($company);
    }

    public function unarchiveCompany(Company $company): Company
    {
        return $company->isArchived() ? $this->unarchive($company) : $company;
    }

    public function restoreCompany(Company $company): Company
    {
        return $company->isDeleted() ? $this->restore($company) : $company;
    }

    /**
     * @return Builder<Company>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Company::query()->with(['country', 'address.country']), 'companies', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        $usage = array_filter($this->usage($model));
        if ($usage !== []) {
            throw new CompanyInUseException(context: ['usage' => $usage]);
        }
    }

    private function assertCountry(CompanyData $data): void
    {
        $code = $data->values['country_code'] ?? null;
        if ($code !== null && ! $this->countries->isActive((string) $code)) {
            throw ValidationException::withMessages(['country_code' => [__('validation.exists', ['attribute' => 'country'])]]);
        }
    }
}
