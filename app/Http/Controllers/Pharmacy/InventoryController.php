<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pharmacy;

use App\Enums\Pharmacy\StockLocation;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Pharmacy\ReceiveStockRequest;
use App\Http\Requests\Pharmacy\StockAdjustmentRequest;
use App\Http\Resources\Pharmacy\MedicationBatchResource;
use App\Http\Resources\Pharmacy\StockMovementResource;
use App\Interfaces\Pharmacy\InventoryServiceInterface;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\StockMovement;
use App\Utilities\Pharmacy\Qty;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InventoryController
{
    use ApiResponse;

    public function __construct(private readonly InventoryServiceInterface $inventory) {}

    public function stock(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, MedicationBatch::listQueryConfig());

        return $this->paginated(MedicationBatchResource::collection($this->inventory->stock($query)), $query);
    }

    public function show(string $batch): JsonResponse
    {
        return $this->ok(new MedicationBatchResource($this->inventory->findBatch($batch)));
    }

    public function movements(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, StockMovement::listQueryConfig());

        return $this->paginated(StockMovementResource::collection($this->inventory->movements($query)), $query);
    }

    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        return $this->created(new MedicationBatchResource($this->inventory->receive($request->toData())));
    }

    public function adjust(StockAdjustmentRequest $request, string $batch): JsonResponse
    {
        return $this->created(new StockMovementResource($this->inventory->adjust($this->inventory->findBatch($batch), $request->toData())));
    }

    /** `quantity` and `to` (pharmacy | treatment_room): moves units of this lot to the other place. */
    public function transfer(Request $request, string $batch): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:100000'],
            'to' => ['required', Rule::enum(StockLocation::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->ok(new MedicationBatchResource($this->inventory->transfer(
            $this->inventory->findBatch($batch),
            Qty::of($request->string('quantity')->toString()),
            StockLocation::from($request->string('to')->toString()),
            $request->string('reason')->toString() ?: null,
        )));
    }

    public function quarantine(Request $request, string $batch): JsonResponse
    {
        $request->validate(['quarantined' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:255']]);

        return $this->ok(new MedicationBatchResource($this->inventory->setQuarantine($this->inventory->findBatch($batch), $request->boolean('quarantined'), $request->string('reason')->toString())));
    }
}
