<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Http\Controllers;

use App\Modules\Appointments\Appointments\Contracts\AppointmentServiceInterface;
use App\Modules\Appointments\Records\Contracts\MedicalRecordServiceInterface;
use App\Modules\Appointments\Records\Http\Requests\MedicalRecordRequest;
use App\Modules\Appointments\Records\Http\Resources\Icd10Resource;
use App\Modules\Appointments\Records\Http\Resources\MedicalRecordResource;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The record follows its appointment: whoever may see the visit (and has records rights) sees its record. */
final class MedicalRecordController
{
    use ApiResponse;

    public function __construct(
        private readonly MedicalRecordServiceInterface $records,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function show(string $appointment): JsonResponse
    {
        return $this->ok(new MedicalRecordResource($this->records->get($this->appointments->find($appointment))));
    }

    public function save(MedicalRecordRequest $request, string $appointment): JsonResponse
    {
        return $this->ok(new MedicalRecordResource($this->records->save($this->appointments->find($appointment), $request->toData())));
    }

    public function sign(string $appointment): JsonResponse
    {
        return $this->ok(new MedicalRecordResource($this->records->sign($this->appointments->find($appointment))));
    }

    public function addAddendum(Request $request, string $appointment): JsonResponse
    {
        $request->validate(['text' => ['required', 'string', 'max:5000']]);
        $visit = $this->appointments->find($appointment);
        $this->records->addAddendum($visit, $request->string('text')->toString());

        return $this->created(new MedicalRecordResource($this->records->get($visit)));
    }

    public function icd10(Request $request): JsonResponse
    {
        $request->validate(['search' => ['required', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return $this->ok(Icd10Resource::collection($this->records->searchIcd10($request->string('search')->toString(), $request->integer('limit', 20))));
    }
}
