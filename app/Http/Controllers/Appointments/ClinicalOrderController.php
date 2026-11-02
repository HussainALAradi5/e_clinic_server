<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Dtos\Appointments\OrderResultData;
use App\Enums\Appointments\ClinicalOrderType;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\ClinicalOrderRequest;
use App\Http\Resources\Appointments\ClinicalOrderResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Appointments\ClinicalOrderServiceInterface;
use App\Models\Appointments\ClinicalOrder;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ClinicalOrderController
{
    use ApiResponse;

    public function __construct(
        private readonly ClinicalOrderServiceInterface $orders,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, ClinicalOrder::listQueryConfig());

        return $this->paginated(ClinicalOrderResource::collection($this->orders->list($query)), $query);
    }

    public function worklist(Request $request): JsonResponse
    {
        $request->validate(['types' => ['nullable', 'array'], 'types.*' => [Rule::enum(ClinicalOrderType::class)]]);
        $types = array_map(fn (mixed $t): ClinicalOrderType => ClinicalOrderType::from((string) $t), array_values((array) $request->input('types', [])));

        return $this->ok(ClinicalOrderResource::collection($this->orders->worklist($types)));
    }

    public function show(string $order): JsonResponse
    {
        return $this->ok(new ClinicalOrderResource($this->orders->find($order)));
    }

    public function store(ClinicalOrderRequest $request): JsonResponse
    {
        return $this->created(new ClinicalOrderResource($this->orders->create($request->toData())));
    }

    public function update(ClinicalOrderRequest $request, string $order): JsonResponse
    {
        return $this->ok(new ClinicalOrderResource($this->orders->update($this->orders->find($order), $request->toData(), $request->integer('version'))));
    }

    public function start(Request $request, string $order): JsonResponse
    {
        $request->validate(['room_id' => ['nullable', 'uuid']]);

        return $this->ok(new ClinicalOrderResource($this->orders->start($this->orders->find($order), $request->filled('room_id') ? $request->string('room_id')->toString() : null)));
    }

    public function complete(Request $request, string $order): JsonResponse
    {
        $request->validate([
            'report' => ['required', 'string', 'max:10000'],
            'is_abnormal' => ['nullable', 'boolean'],
            'reported_by' => ['nullable', 'uuid', Rule::exists('doctors', 'id')],
        ]);

        return $this->ok(new ClinicalOrderResource($this->orders->complete($this->orders->find($order), new OrderResultData(
            $request->string('report')->toString(),
            $request->has('is_abnormal') ? $request->boolean('is_abnormal') : null,
            $request->filled('reported_by') ? $request->string('reported_by')->toString() : null,
        ))));
    }

    public function linkReferralAppointment(Request $request, string $order): JsonResponse
    {
        $request->validate(['appointment_id' => ['required', 'uuid']]);
        $appointment = $this->appointments->find($request->string('appointment_id')->toString());

        return $this->ok(new ClinicalOrderResource($this->orders->linkReferralAppointment($this->orders->find($order), $appointment)));
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new ClinicalOrderResource($this->orders->cancel($this->orders->find($order), $request->string('reason')->toString())));
    }

    public function destroy(string $order): Response
    {
        $this->orders->delete($this->orders->find($order));

        return $this->noContent();
    }

    public function archive(string $order): JsonResponse
    {
        return $this->ok(new ClinicalOrderResource($this->orders->archive($this->orders->find($order))));
    }

    public function unarchive(string $order): JsonResponse
    {
        return $this->ok(new ClinicalOrderResource($this->orders->unarchive($this->orders->find($order))));
    }

    public function restore(string $order): JsonResponse
    {
        return $this->ok(new ClinicalOrderResource($this->orders->restore($this->orders->findWithDeleted($order))));
    }
}
