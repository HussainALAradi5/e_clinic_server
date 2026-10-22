<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Pharmacy\StockMovementType;
use App\Enums\Transactions\ExpenseCategory;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Data\ReceiveStockData;
use App\Modules\Pharmacy\Inventory\Data\StockAdjustmentData;
use App\Modules\Pharmacy\Inventory\Data\StockIssue;
use App\Modules\Pharmacy\Inventory\Exceptions\BatchExpiredException;
use App\Modules\Pharmacy\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Inventory\Models\StockMovement;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Support\Qty;
use App\Modules\Transactions\Expenses\Contracts\ExpenseServiceInterface;
use App\Modules\Transactions\Expenses\Data\ExpenseData;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Shared\Access\OwnershipScope;
use App\Shared\Exceptions\SelfApprovalException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class InventoryService implements InventoryServiceInterface
{
    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ExpenseServiceInterface $expenses,
        private readonly ClinicClock $clock,
    ) {}

    public function stock(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->batches(), $query);
    }

    public function findBatch(string $id): MedicationBatch
    {
        return $this->batches()->with('expense')->findOrFail($id);
    }

    public function movements(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $builder = OwnershipScope::apply(StockMovement::query()->with(['medication', 'batch', 'creator.person', 'approver.person']), 'inventory', Auth::user());

        return $this->listQuery->paginate($builder, $query);
    }

    public function receive(ReceiveStockData $data): MedicationBatch
    {
        $batch = DB::transaction(function () use ($data): MedicationBatch {
            $medication = Medication::query()->where('is_archived', false)->findOrFail($data->medicationId);
            $cost = MoneyHelper::of($data->unitCost);

            $batch = MedicationBatch::query()->where('medication_id', $medication->id)->where('batch_number', $data->batchNumber)->lockForUpdate()->first();
            if ($batch !== null && ! $batch->expiry_date->isSameDay($data->expiryDate)) {
                throw ValidationException::withMessages(['expiry_date' => [__('validation.same', ['attribute' => 'expiry date', 'other' => "lot {$batch->code} ({$batch->expiry_date->toDateString()})"])]]);
            }

            if ($batch === null) {
                $batch = MedicationBatch::query()->create([
                    'medication_id' => $medication->id,
                    'batch_number' => $data->batchNumber,
                    'expiry_date' => $data->expiryDate,
                    'qty_received' => (string) $data->quantity,
                    'qty_on_hand' => '0',
                    'unit_cost' => $cost,
                    'supplier_id' => $data->supplierId,
                    'received_at' => $this->clock->now(),
                ]);
            } else {
                // Another delivery of the same lot: one lot, weighted average cost.
                $held = Qty::of($batch->qty_on_hand);
                $total = $held->plus($data->quantity);
                $batch->forceFill([
                    'qty_received' => (string) Qty::of($batch->qty_received)->plus($data->quantity),
                    'unit_cost' => $total->isZero() ? $cost : MoneyHelper::of($batch->unit_cost->getAmount()->multipliedBy($held)
                        ->plus($cost->getAmount()->multipliedBy($data->quantity))
                        ->dividedBy($total, 3, RoundingMode::HALF_UP)),
                    'supplier_id' => $data->supplierId ?? $batch->supplier_id,
                    'received_at' => $this->clock->now(),
                ])->save();
            }
            $this->record($batch, StockMovementType::Receipt, $data->quantity, $batch);

            if ($data->createExpense && $this->value($cost, $data->quantity)->isPositive()) {
                $batch->expense_id = $this->purchaseExpense($batch, $medication, $data, $cost)->id;
                $batch->save();
            }

            return $batch;
        });

        return $this->findBatch($batch->id);
    }

    public function adjust(MedicationBatch $batch, StockAdjustmentData $data): StockMovement
    {
        return DB::transaction(function () use ($batch, $data): StockMovement {
            $batch = MedicationBatch::query()->with('medication')->lockForUpdate()->findOrFail($batch->id);
            $after = Qty::of($batch->qty_on_hand)->plus($data->quantity);
            if ($after->isNegative()) {
                throw new InsufficientStockException(
                    __('errors.INSUFFICIENT_STOCK', ['medication' => $batch->medication->displayName(), 'available' => Qty::str($batch->qty_on_hand)]),
                    ['medication_id' => $batch->medication_id, 'available' => Qty::str($batch->qty_on_hand)],
                );
            }
            if ($after->isGreaterThan(Qty::of($batch->qty_received))) {
                throw ValidationException::withMessages(['quantity' => [__('errors.STOCK_ABOVE_RECEIVED', ['received' => Qty::str($batch->qty_received)])]]);
            }
            $approver = $this->approver($batch, $data);

            $movement = $this->record($batch, StockMovementType::Adjustment, $data->quantity, $batch, $data->reason, $approver);
            $value = $this->value($batch->unit_cost, $data->quantity->abs());
            if ($value->isPositive()) {
                [$debit, $credit] = $data->quantity->isNegative()
                    ? [AccountMappingKey::CostOfGoodsSold, AccountMappingKey::PharmacyInventory]
                    : [AccountMappingKey::PharmacyInventory, AccountMappingKey::CostOfGoodsSold];
                $this->journal->postAutomatic(new AutomaticEntryData($batch, $this->clock->today(), __('accounting.stock_adjusted', ['lot' => $batch->code, 'reason' => $data->reason]), [
                    JournalLineData::debit($debit, $value),
                    JournalLineData::credit($credit, $value),
                ]));
            }

            return $movement->load(['medication', 'batch', 'creator.person', 'approver.person']);
        });
    }

    public function setQuarantine(MedicationBatch $batch, bool $quarantined, string $reason): MedicationBatch
    {
        DB::transaction(function () use ($batch, $quarantined, $reason): void {
            $batch = MedicationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $batch->forceFill(['is_quarantined' => $quarantined, 'quarantine_reason' => $reason])->save();
        });

        return $this->findBatch($batch->id);
    }

    public function writeOffExpired(CarbonImmutable $asOf): int
    {
        $count = 0;
        $expired = MedicationBatch::query()->where('qty_on_hand', '>', 0)->whereDate('expiry_date', '<', $asOf->toDateString())->get(['id']);
        foreach ($expired as $lot) {
            try {
                DB::transaction(function () use ($lot, $asOf): void {
                    $batch = MedicationBatch::query()->lockForUpdate()->findOrFail($lot->id);
                    $quantity = Qty::of($batch->qty_on_hand);
                    if (! $quantity->isPositive()) {
                        return;
                    }
                    $this->record($batch, StockMovementType::ExpiryWriteoff, $quantity->negated(), $batch, 'Expired '.$batch->expiry_date->toDateString());
                    $value = $this->value($batch->unit_cost, $quantity);
                    if ($value->isPositive()) {
                        $this->journal->postAutomatic(new AutomaticEntryData($batch, $asOf, __('accounting.stock_written_off', ['lot' => $batch->code]), [
                            JournalLineData::debit(AccountMappingKey::CostOfGoodsSold, $value),
                            JournalLineData::credit(AccountMappingKey::PharmacyInventory, $value),
                        ]));
                    }
                });
                $count++;
            } catch (Throwable $e) {
                // One lot (e.g. a closed fiscal period) never stops the others; the next run retries it.
                report($e);
            }
        }

        return $count;
    }

    public function issue(Medication $medication, BigDecimal $quantity, ?string $batchId, Model $source): array
    {
        $today = $this->clock->today();
        if ($batchId !== null) {
            $batch = MedicationBatch::query()->lockForUpdate()->find($batchId);
            if (! $batch instanceof MedicationBatch || $batch->medication_id !== $medication->id || $batch->is_deleted || ! $batch->isDispensable($today)) {
                throw new BatchExpiredException(__('errors.BATCH_EXPIRED', ['batch' => $batch->code ?? $batchId]), ['batch_id' => $batchId]);
            }
            $batches = collect([$batch]);
        } else {
            $batches = MedicationBatch::query()->where('medication_id', $medication->id)->dispensable($today)
                ->where('qty_on_hand', '>', 0)->orderBy('expiry_date')->orderBy('received_at')->lockForUpdate()->get();
        }

        $available = $batches->reduce(fn (BigDecimal $sum, MedicationBatch $b): BigDecimal => $sum->plus(Qty::of($b->qty_on_hand)), Qty::zero());
        if ($available->isLessThan($quantity)) {
            throw new InsufficientStockException(
                __('errors.INSUFFICIENT_STOCK', ['medication' => $medication->displayName(), 'available' => (string) $available]),
                ['medication_id' => $medication->id, 'available' => (string) $available],
            );
        }

        $issues = [];
        $left = $quantity;
        foreach ($batches as $batch) {
            if (! $left->isPositive()) {
                break;
            }
            $take = BigDecimal::min($left, Qty::of($batch->qty_on_hand));
            if (! $take->isPositive()) {
                continue;
            }
            $this->record($batch, StockMovementType::Dispense, $take->negated(), $source);
            $issues[] = new StockIssue($batch, $take);
            $left = $left->minus($take);
        }

        return $issues;
    }

    public function restock(MedicationBatch $batch, BigDecimal $quantity, Model $source, string $reason): void
    {
        $batch = MedicationBatch::query()->lockForUpdate()->findOrFail($batch->id);
        $this->record($batch, StockMovementType::Return, $quantity, $source, $reason);
    }

    /**
     * @return Builder<MedicationBatch>
     */
    private function batches(): Builder
    {
        return OwnershipScope::apply(MedicationBatch::query()->with(['medication', 'supplier']), 'inventory', Auth::user());
    }

    /** Ledger row + on-hand change of a locked lot. */
    private function record(MedicationBatch $batch, StockMovementType $type, BigDecimal $quantity, Model $source, ?string $reason = null, ?string $approvedBy = null): StockMovement
    {
        $batch->forceFill(['qty_on_hand' => (string) Qty::of($batch->qty_on_hand)->plus($quantity)])->save();

        return StockMovement::query()->create([
            'medication_id' => $batch->medication_id,
            'batch_id' => $batch->id,
            'type' => $type,
            'quantity' => (string) $quantity,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'reason' => $reason,
            'approved_by' => $approvedBy,
            'created_by' => Auth::id(),
        ]);
    }

    /** Large corrections and controlled drugs need another pharmacist (segregation of duties). */
    private function approver(MedicationBatch $batch, StockAdjustmentData $data): ?string
    {
        $limit = Config::integer('pharmacy.adjustment_approval_threshold');
        $needed = $batch->medication->is_controlled || $data->quantity->abs()->isGreaterThan($limit);
        if (! $needed) {
            return null;
        }
        $approver = $data->approvedBy === null ? null : User::query()->find($data->approvedBy);
        if (! $approver instanceof User || $approver->id === Auth::id() || ! $approver->can('inventory.adjust')) {
            throw new SelfApprovalException(__('errors.ADJUSTMENT_NEEDS_APPROVAL', ['limit' => $limit]), ['limit' => $limit]);
        }

        return $approver->id;
    }

    private function value(Money $unitCost, BigDecimal $quantity): Money
    {
        return $unitCost->multipliedBy($quantity, RoundingMode::HALF_UP);
    }

    /** Purchase booked as a submitted expense on the pharmacy inventory account (paid later by finance). */
    private function purchaseExpense(MedicationBatch $batch, Medication $medication, ReceiveStockData $data, Money $cost): Expense
    {
        $expense = $this->expenses->create(ExpenseData::fromArray([
            'category' => ExpenseCategory::PharmacyPurchase->value,
            'payee_company_id' => $data->supplierId,
            'payee' => $data->supplierId === null ? ExpenseCategory::PharmacyPurchase->label() : null,
            'description' => __('accounting.stock_purchase', ['lot' => $batch->code, 'medication' => $medication->displayName(), 'quantity' => (string) $data->quantity]),
            'amount' => MoneyHelper::toDb($this->value($cost, $data->quantity)),
            'tax_amount' => '0',
            'expense_date' => $this->clock->today()->toDateString(),
            'vendor_invoice_number' => $data->vendorInvoiceNumber,
        ]));

        return $this->expenses->submit($expense);
    }
}
