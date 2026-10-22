<?php

declare(strict_types=1);

namespace App\Modules\Reference;

use App\Modules\Reference\Addresses\Contracts\AddressServiceInterface;
use App\Modules\Reference\Addresses\Services\AddressService;
use App\Modules\Reference\Companies\Contracts\CompanyServiceInterface;
use App\Modules\Reference\Companies\Http\Resources\CompanyResource;
use App\Modules\Reference\Companies\Imports\CompanyImport;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Reference\Companies\Services\CompanyService;
use App\Modules\Reference\Countries\Contracts\CountryServiceInterface;
use App\Modules\Reference\Countries\Http\Resources\CountryResource;
use App\Modules\Reference\Countries\Models\Country;
use App\Modules\Reference\Countries\Services\CountryService;
use App\Shared\Access\PermissionExpression;
use App\Shared\Bulk\BulkActionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Reference Data module: countries, the shared address model and companies. Used by every other module.
 */
final class ReferenceDataServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        CountryServiceInterface::class => CountryService::class,
        AddressServiceInterface::class => AddressService::class,
        CompanyServiceInterface::class => CompanyService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        $bulk->register('countries', Country::class, Country::listQueryConfig())->readableBy(PermissionExpression::AUTHENTICATED)
            ->permissionsOf('reference')
            ->exportable(CountryResource::class);
        $bulk->register('companies', Company::class, Company::listQueryConfig(), CompanyService::class)
            ->exportable(CompanyResource::class)
            ->importable(CompanyImport::class);
    }
}
