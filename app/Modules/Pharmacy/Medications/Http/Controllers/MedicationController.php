<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Http\Controllers;

use App\Modules\Pharmacy\Medications\Contracts\MedicationServiceInterface;
use App\Modules\Pharmacy\Medications\Http\Requests\MedicationRequest;
use App\Modules\Pharmacy\Medications\Http\Resources\MedicationResource;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MedicationController
{
    use ApiResponse;

    public function __construct(private readonly MedicationServiceInterface $medications) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Medication::listQueryConfig());

        return $this->paginated(MedicationResource::collection($this->medications->list($query)), $query);
    }

    public function lowStock(): JsonResponse
    {
        return $this->ok(MedicationResource::collection($this->medications->lowStock()));
    }

    public function byBarcode(string $gtin): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->findByBarcode($gtin)));
    }

    public function show(string $medication): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->find($medication)));
    }

    public function store(MedicationRequest $request): JsonResponse
    {
        return $this->created(new MedicationResource($this->medications->create($request->toData())));
    }

    public function update(MedicationRequest $request, string $medication): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->update($this->medications->find($medication), $request->toData())));
    }

    public function destroy(string $medication): Response
    {
        $this->medications->delete($this->medications->find($medication));

        return $this->noContent();
    }

    public function archive(string $medication): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->archive($this->medications->find($medication))));
    }

    public function unarchive(string $medication): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->unarchive($this->medications->find($medication))));
    }

    public function restore(string $medication): JsonResponse
    {
        return $this->ok(new MedicationResource($this->medications->restore($this->medications->findWithDeleted($medication))));
    }
}
