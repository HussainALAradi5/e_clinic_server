<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\RefundRequest;
use App\Http\Resources\Payments\RefundResource;
use App\Interfaces\Payments\RefundServiceInterface;
use App\Models\Payments\Refund;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RefundController
{
    use ApiResponse;

    public function __construct(private readonly RefundServiceInterface $refunds) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Refund::listQueryConfig());

        return $this->paginated(RefundResource::collection($this->refunds->list($query)), $query);
    }

    public function show(string $refund): JsonResponse
    {
        return $this->ok(new RefundResource($this->refunds->find($refund)));
    }

    public function store(RefundRequest $request): JsonResponse
    {
        return $this->created(new RefundResource($this->refunds->request($request->toData())));
    }

    public function approve(string $refund): JsonResponse
    {
        return $this->ok(new RefundResource($this->refunds->approve($this->refunds->find($refund))));
    }

    public function reject(Request $request, string $refund): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new RefundResource($this->refunds->reject($this->refunds->find($refund), $request->string('reason')->toString())));
    }

    public function process(Request $request, string $refund): JsonResponse
    {
        $request->validate(['reference_number' => ['nullable', 'string', 'max:100']]);

        return $this->ok(new RefundResource($this->refunds->process(
            $this->refunds->find($refund),
            $request->filled('reference_number') ? $request->string('reference_number')->toString() : null,
        )));
    }
}
