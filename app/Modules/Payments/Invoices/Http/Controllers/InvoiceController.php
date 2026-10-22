<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Http\Controllers;

use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Payments\Invoices\Http\Requests\InvoiceRequest;
use App\Modules\Payments\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController
{
    use ApiResponse;

    public function __construct(private readonly InvoiceServiceInterface $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Invoice::listQueryConfig());

        return $this->paginated(InvoiceResource::collection($this->invoices->list($query)), $query);
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
