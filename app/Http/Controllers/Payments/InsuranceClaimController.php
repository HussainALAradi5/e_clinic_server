<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Dtos\Payments\ClaimDecisionData;
use App\Dtos\Payments\RemittanceData;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\Payments\InsuranceClaimResource;
use App\Interfaces\Payments\InsuranceClaimServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Models\Payments\InsuranceClaim;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InsuranceClaimController
{
    use ApiResponse;

    public function __construct(private readonly InsuranceClaimServiceInterface $claims) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, InsuranceClaim::listQueryConfig());

        return $this->paginated(InsuranceClaimResource::collection($this->claims->list($query)), $query);
    }

    /** Diagnoses (ICD-10 of the visits) only for claim staff, never for the patient's own view. */
    public function show(Request $request, string $claim): JsonResponse
    {
        $model = $this->claims->find($claim);
        $resource = new InsuranceClaimResource($model);
        if ($request->user()?->can('claims.read_all') === true) {
            $resource->withDiagnoses($this->claims->diagnoses($model));
        }

        return $this->ok($resource);
    }

    public function store(Request $request, InvoiceServiceInterface $invoices): JsonResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'uuid', Rule::exists('invoices', 'id')->where('is_deleted', 0)],
            'pre_authorization_number' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->created(new InsuranceClaimResource($this->claims->createForInvoice(
            $invoices->find($request->string('invoice_id')->toString()),
            $request->filled('pre_authorization_number') ? $request->string('pre_authorization_number')->toString() : null,
        )));
    }

    public function submit(string $claim): JsonResponse
    {
        return $this->ok(new InsuranceClaimResource($this->claims->submit($this->claims->find($claim))));
    }

    public function decide(Request $request, string $claim): JsonResponse
    {
        $request->validate([
            'approved' => ['required', 'boolean'],
            'approved_amount' => ['nullable', 'decimal:0,3', 'gt:0'],
            'insurer_reference' => ['nullable', 'string', 'max:100'],
            'rejection_reason' => ['required_if:approved,false,0', 'nullable', 'string', 'max:500'],
        ]);
        $data = new ClaimDecisionData(
            $request->boolean('approved'),
            $request->filled('approved_amount') ? $request->string('approved_amount')->toString() : null,
            $request->filled('insurer_reference') ? $request->string('insurer_reference')->toString() : null,
            $request->filled('rejection_reason') ? $request->string('rejection_reason')->toString() : null,
        );

        return $this->ok(new InsuranceClaimResource($this->claims->decide($this->claims->find($claim), $data)));
    }

    public function remittance(Request $request, ClinicClock $clock): JsonResponse
    {
        $request->validate([
            'claim_ids' => ['required', 'array', 'min:1', 'max:500'],
            'claim_ids.*' => ['uuid'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
        ]);
        /** @var list<string> $ids */
        $ids = array_values($request->input('claim_ids'));
        $data = new RemittanceData(
            $ids,
            CarbonImmutable::parse($request->string('date')->toString(), $clock->timezone())->startOfDay(),
            $request->string('reference')->toString(),
            $request->string('amount')->toString(),
        );

        return $this->ok(InsuranceClaimResource::collection($this->claims->recordRemittance($data)));
    }

    public function cancel(Request $request, string $claim): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new InsuranceClaimResource($this->claims->cancel($this->claims->find($claim), $request->string('reason')->toString())));
    }
}
