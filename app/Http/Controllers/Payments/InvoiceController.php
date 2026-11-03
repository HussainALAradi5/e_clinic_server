<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Enums\Payments\InvoicePaymentState;
use App\Enums\Shared\PaymentMethod;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\InvoiceRequest;
use App\Http\Resources\Payments\InvoiceResource;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Models\Payments\Invoice;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InvoiceController
{
    use ApiResponse;

    public function __construct(private readonly InvoiceServiceInterface $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'payment_state' => ['nullable', Rule::enum(InvoicePaymentState::class)],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ]);
        $query = ListQuery::fromRequest($request, Invoice::listQueryConfig());
        $state = $request->filled('payment_state') ? InvoicePaymentState::from($request->string('payment_state')->toString()) : null;
        $method = $request->filled('method') ? PaymentMethod::from($request->string('method')->toString()) : null;

        return $this->paginated(InvoiceResource::collection($this->invoices->list($query, $state, $method)), $query);
    }

    public function outstanding(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Invoice::listQueryConfig());

        return $this->paginated(InvoiceResource::collection($this->invoices->outstanding($query)), $query);
    }

    public function show(string $invoice): JsonResponse
    {
        return $this->ok(new InvoiceResource($this->invoices->find($invoice)));
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        return $this->created(new InvoiceResource($this->invoices->createDraft($request->toData())));
    }

    public function charge(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => ['required', 'in:appointment,clinical_order'],
            'source_id' => ['required', 'uuid'],
        ]);

        return $this->ok(new InvoiceResource($this->invoices->chargeSource($request->string('source_type')->toString(), $request->string('source_id')->toString())));
    }

    public function update(InvoiceRequest $request, string $invoice): JsonResponse
    {
        $model = $this->invoices->find($invoice);

        return $this->ok(new InvoiceResource($this->invoices->updateDraft($model, $request->toData($model->patient_id), $request->integer('version'))));
    }

    public function issue(string $invoice): JsonResponse
    {
        return $this->ok(new InvoiceResource($this->invoices->issue($this->invoices->find($invoice))));
    }

    public function cancel(Request $request, string $invoice): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new InvoiceResource($this->invoices->cancel($this->invoices->find($invoice), $request->string('reason')->toString())));
    }
}
