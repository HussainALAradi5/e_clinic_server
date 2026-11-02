<?php

declare(strict_types=1);

namespace App\Modules\Reference;

use App\Http\Resources\Reference\CompanyResource;
use App\Http\Resources\Reference\CountryResource;
use App\Imports\Reference\CompanyImport;
use App\Interfaces\Reference\AddressServiceInterface;
use App\Interfaces\Reference\CompanyServiceInterface;
use App\Interfaces\Reference\CountryServiceInterface;
use App\Models\Reference\Company;
use App\Models\Reference\Country;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Reference\AddressService;
use App\Services\Reference\CompanyService;
use App\Services\Reference\CountryService;
use App\Utilities\Access\PermissionExpression;
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
