<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Requests\Payments\PaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Models\Payments\Payment;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController
{
    use ApiResponse;

    public function __construct(private readonly PaymentServiceInterface $payments) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Payment::listQueryConfig());

        return $this->paginated(PaymentResource::collection($this->payments->list($query)), $query);
    }

    /** End-of-day takings per method and cashier; without `payments.read_all` only the caller's own. */
    public function dailySummary(Request $request, ClinicClock $clock): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date'], 'cashier_id' => ['nullable', 'uuid']]);
        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')->toString(), $clock->timezone())->startOfDay() : $clock->today();
        $cashier = $request->user()?->can('payments.read_all') === true
            ? ($request->filled('cashier_id') ? $request->string('cashier_id')->toString() : null)
            : (string) $request->user()?->getAuthIdentifier();

        $summary = $this->payments->dailySummary($date, $cashier);
        // A list, not a method-keyed map: the client camelCases object keys.
        $summary['totals'] = array_map(fn (string $method, string $total): array => ['method' => $method, 'total' => $total], array_keys($summary['totals']), array_values($summary['totals']));

        return $this->ok($summary);
    }

    public function show(string $payment): JsonResponse
    {
        return $this->ok(new PaymentResource($this->payments->find($payment)));
    }

    public function store(PaymentRequest $request): JsonResponse
    {
        return $this->created(new PaymentResource($this->payments->record($request->toData(), (string) $request->header(EnsureIdempotency::HEADER))));
    }

    public function void(Request $request, string $payment): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new PaymentResource($this->payments->void($this->payments->find($payment), $request->string('reason')->toString())));
    }
}
