<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Http\Controllers;

use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Http\Requests\ReceiveStockRequest;
use App\Modules\Pharmacy\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Pharmacy\Inventory\Http\Resources\MedicationBatchResource;
use App\Modules\Pharmacy\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Inventory\Models\StockMovement;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function quarantine(Request $request, string $batch): JsonResponse
    {
        $request->validate(['quarantined' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:255']]);

        return $this->ok(new MedicationBatchResource($this->inventory->setQuarantine($this->inventory->findBatch($batch), $request->boolean('quarantined'), $request->string('reason')->toString())));
    }
}
