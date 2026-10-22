<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Http\Controllers;

use App\Modules\Pharmacy\Prescriptions\Contracts\PrescriptionServiceInterface;
use App\Modules\Pharmacy\Prescriptions\Http\Requests\PrescriptionRequest;
use App\Modules\Pharmacy\Prescriptions\Http\Resources\PrescriptionResource;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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
