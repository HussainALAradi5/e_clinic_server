<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\ConsentRequest;
use App\Http\Resources\Appointments\ConsentResource;
use App\Interfaces\Appointments\ConsentServiceInterface;
use App\Interfaces\Appointments\PatientServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController
{
    use ApiResponse;

    public function __construct(
        private readonly ConsentServiceInterface $consents,
        private readonly PatientServiceInterface $patients,
    ) {}

    public function index(string $patient): JsonResponse
    {
        return $this->ok(ConsentResource::collection($this->consents->list($this->patients->find($patient))));
    }

    public function store(ConsentRequest $request, string $patient): JsonResponse
    {
        return $this->created(new ConsentResource($this->consents->record($this->patients->find($patient), $request->toData())));
    }

    public function show(string $consent): JsonResponse
    {
        return $this->ok(new ConsentResource($this->consents->find($consent)));
    }

    public function revoke(Request $request, string $consent): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new ConsentResource($this->consents->revoke($this->consents->find($consent), $request->string('reason')->toString())));
    }
}
