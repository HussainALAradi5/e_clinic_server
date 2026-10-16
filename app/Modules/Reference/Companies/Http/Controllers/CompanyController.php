<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Http\Controllers;

use App\Modules\Reference\Companies\Contracts\CompanyServiceInterface;
use App\Modules\Reference\Companies\Http\Requests\CompanyRequest;
use App\Modules\Reference\Companies\Http\Resources\CompanyResource;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CompanyController
{
    use ApiResponse;

    public function __construct(private readonly CompanyServiceInterface $companies) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Company::listQueryConfig());

        return $this->paginated(CompanyResource::collection($this->companies->listCompanies($query)), $query);
    }

    public function show(string $company): JsonResponse
    {
        $model = $this->companies->findCompany($company);

        return $this->ok((new CompanyResource($model))->withUsage($this->companies->usage($model)));
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        return $this->created(new CompanyResource($this->companies->createCompany($request->toData())));
    }

    public function update(CompanyRequest $request, string $company): JsonResponse
    {
        return $this->ok(new CompanyResource($this->companies->updateCompany($this->companies->findCompany($company), $request->toData())));
    }

    public function destroy(string $company): Response
    {
        $this->companies->deleteCompany($this->companies->findCompany($company));

        return $this->noContent();
    }

    public function archive(string $company): JsonResponse
    {
        return $this->ok(new CompanyResource($this->companies->archiveCompany($this->companies->findCompany($company))));
    }

    public function unarchive(string $company): JsonResponse
    {
        return $this->ok(new CompanyResource($this->companies->unarchiveCompany($this->companies->findCompany($company))));
    }

    public function restore(string $company): JsonResponse
    {
        return $this->ok(new CompanyResource($this->companies->restoreCompany($this->companies->findWithDeletedCompany($company))));
    }
}
