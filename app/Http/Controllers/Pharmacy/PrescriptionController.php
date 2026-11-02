<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pharmacy;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Pharmacy\PrescriptionRequest;
use App\Http\Resources\Pharmacy\PrescriptionResource;
use App\Interfaces\Pharmacy\PrescriptionServiceInterface;
use App\Models\Pharmacy\Prescription;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrescriptionController
{
    use ApiResponse;

    public function __construct(private readonly PrescriptionServiceInterface $prescriptions) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Prescription::listQueryConfig());

        return $this->paginated(PrescriptionResource::collection($this->prescriptions->list($query)), $query);
    }

    public function queue(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Prescription::listQueryConfig()->defaultSort('created_at'));

        return $this->paginated(PrescriptionResource::collection($this->prescriptions->queue($query)), $query);
    }

    public function show(string $prescription): JsonResponse
    {
        return $this->ok(new PrescriptionResource($this->prescriptions->find($prescription)));
    }

    public function store(PrescriptionRequest $request): JsonResponse
    {
        return $this->created(new PrescriptionResource($this->prescriptions->create($request->toData())));
    }

    public function update(PrescriptionRequest $request, string $prescription): JsonResponse
    {
        return $this->ok(new PrescriptionResource($this->prescriptions->update($this->prescriptions->find($prescription), $request->toData())));
    }

    public function cancel(Request $request, string $prescription): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new PrescriptionResource($this->prescriptions->cancel($this->prescriptions->find($prescription), $request->string('reason')->toString())));
    }
}
