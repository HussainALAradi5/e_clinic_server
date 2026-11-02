<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\BillableServiceRequest;
use App\Http\Resources\Payments\BillableServiceResource;
use App\Interfaces\Payments\BillableServiceServiceInterface;
use App\Models\Payments\BillableService;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BillableServiceController
{
    use ApiResponse;

    public function __construct(private readonly BillableServiceServiceInterface $services) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, BillableService::listQueryConfig());

        return $this->paginated(BillableServiceResource::collection($this->services->listServices($query)), $query);
    }

    public function show(string $service): JsonResponse
    {
        $model = $this->services->findService($service);

        return $this->ok((new BillableServiceResource($model))->withUsage($this->services->usage($model)));
    }

    public function store(BillableServiceRequest $request): JsonResponse
    {
        return $this->created(new BillableServiceResource($this->services->createService($request->toData())));
    }

    public function update(BillableServiceRequest $request, string $service): JsonResponse
    {
        return $this->ok(new BillableServiceResource($this->services->updateService($this->services->findService($service), $request->toData())));
    }

    public function destroy(string $service): Response
    {
        $this->services->deleteService($this->services->findService($service));

        return $this->noContent();
    }

    public function archive(string $service): JsonResponse
    {
        return $this->ok(new BillableServiceResource($this->services->archiveService($this->services->findService($service))));
    }

    public function unarchive(string $service): JsonResponse
    {
        return $this->ok(new BillableServiceResource($this->services->unarchiveService($this->services->findService($service))));
    }

    public function restore(string $service): JsonResponse
    {
        return $this->ok(new BillableServiceResource($this->services->restoreService($this->services->findWithDeletedService($service))));
    }
}
