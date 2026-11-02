<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Contracts;

use App\Enums\Pharmacy\StockLocation;
use App\Modules\Pharmacy\Inventory\Data\ReceiveStockData;
use App\Modules\Pharmacy\Inventory\Data\StockAdjustmentData;
use App\Modules\Pharmacy\Inventory\Data\StockIssue;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Inventory\Models\StockMovement;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Shared\Query\ListQuery;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Batch-level stock and the append-only movement ledger. The only code that changes `qty_on_hand`: every change
 * writes a stock_movements row in the same transaction (on hand = Σ movements).
 */
interface InventoryServiceInterface
{
    /**
     * Lots with medication, on-hand quantity and expiry status (expired / expiring soon / ok).
     *
     * @return LengthAwarePaginator<int, MedicationBatch>|CursorPaginator<int, MedicationBatch>
     */
    public function stock(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findBatch(string $id): MedicationBatch;

    /**
     * Stock ledger (cursor pagination).
     *
     * @return LengthAwarePaginator<int, StockMovement>|CursorPaginator<int, StockMovement>
     */
    public function movements(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Goods receipt: a new lot (or a top-up of the same lot and expiry) and a `receipt` movement; optional purchase expense. */
    public function receive(ReceiveStockData $data): MedicationBatch;

    /** Count correction (+/−) with reason; posts the value difference (inventory ↔ cost of goods sold). */
    public function adjust(MedicationBatch $batch, StockAdjustmentData $data): StockMovement;

    /** Blocks (recall, damage) or releases a lot; blocked lots are never dispensed. */
    public function setQuarantine(MedicationBatch $batch, bool $quarantined, string $reason): MedicationBatch;

    /** **Scheduled daily** — empties expired lots with `expiry_writeoff` movements and a journal entry each; returns how many. */
    public function writeOffExpired(CarbonImmutable $asOf): int;

    /**
     * **Internal (dispensing)** — takes stock first-expiry-first-out (or from the scanned lot), locking the lots.
     *
     * @return list<StockIssue>
     */
    public function issue(Medication $medication, BigDecimal $quantity, ?string $batchId, Model $source, StockLocation $from = StockLocation::Pharmacy): array;

    /**
     * Moves units of a lot to the other place (pharmacy ↔ treatment room): the same batch, expiry and cost there,
     * one lot per place, `transfer` movements on both. Returns the lot at [$to].
     */
    public function transfer(MedicationBatch $batch, BigDecimal $quantity, StockLocation $to, ?string $reason = null): MedicationBatch;

    /** **Internal (dispensing cancel)** — puts a returned quantity back on its lot (`return` movement). */
    public function restock(MedicationBatch $batch, BigDecimal $quantity, Model $source, string $reason): void;
}
