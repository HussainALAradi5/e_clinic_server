<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\WaiveFeeRequest;
use App\Http\Resources\Payments\AppointmentFeeResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Payments\AppointmentFeeServiceInterface;
use App\Models\Payments\AppointmentFee;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fees for missed and late-cancelled visits: the list, what cancelling now would cost, and waiving one.
 */
final class AppointmentFeeController
{
    use ApiResponse;

    public function __construct(
        private readonly AppointmentFeeServiceInterface $fees,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, AppointmentFee::listQueryConfig());

        return $this->paginated(AppointmentFeeResource::collection($this->fees->list($query)), $query);
    }

    public function show(string $fee): JsonResponse
    {
        return $this->ok(new AppointmentFeeResource($this->fees->find($fee)));
    }

    /** `{ amount: Money|null }`: the late-cancellation fee if the patient cancelled this visit now. */
    public function cancellationFee(string $appointment): JsonResponse
    {
        $amount = $this->fees->cancellationFee($this->appointments->find($appointment));

        return $this->ok(['amount' => $amount === null ? null : MoneyHelper::toApi($amount)]);
    }

    public function waive(WaiveFeeRequest $request, string $fee): JsonResponse
    {
        return $this->ok(new AppointmentFeeResource($this->fees->waive($this->fees->find($fee), $request->reason())));
    }

    /** `[{ kind, name, amount }]`: the documents that cost money now (shown before a doctor issues one). */
    public function documentFees(): JsonResponse
    {
        return $this->ok($this->fees->documentFees());
    }
}
