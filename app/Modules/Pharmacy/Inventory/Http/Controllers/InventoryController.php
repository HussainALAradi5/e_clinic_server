<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Http\Controllers;

use App\Enums\Pharmacy\StockLocation;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Http\Requests\ReceiveStockRequest;
use App\Modules\Pharmacy\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Pharmacy\Inventory\Http\Resources\MedicationBatchResource;
use App\Modules\Pharmacy\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Inventory\Models\StockMovement;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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
