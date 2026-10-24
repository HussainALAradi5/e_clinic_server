<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Models;

use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Transactions\Expenses\Models\Expense;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Stock lot of a medication: manufacturer lot number, expiry, purchase cost. `qty_on_hand` changes only through
 * InventoryService together with a stock_movements row. The code (`LOT-YYYY-NNNNNN`) is printed on the shelf label.
 *
 * @property string $id
 * @property string $code
 * @property string $medication_id
 * @property string $batch_number
 * @property CarbonImmutable $expiry_date
 * @property string $qty_received
 * @property string $qty_on_hand
 * @property Money $unit_cost
 * @property ?string $supplier_id
 * @property CarbonImmutable $received_at
 * @property ?string $expense_id
 * @property bool $is_quarantined
 * @property ?string $quarantine_reason
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Medication $medication
 * @property-read ?Company $supplier
 * @property-read ?Expense $expense
 */
class MedicationBatch extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'LOT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('inventory')
            ->sortable('code', 'batch_number', 'expiry_date', 'qty_on_hand', 'received_at')
            ->filterable('medication_id', ['eq', 'in'], resource: 'medications')
            ->filterable('supplier_id', ['eq', 'in', 'null'], resource: 'companies')
            ->filterable('expiry_date', ['lt', 'lte', 'gte', 'between'], type: 'date')
            ->filterable('qty_on_hand', ['gt', 'gte', 'lte', 'eq'], type: 'number')
            ->filterable('is_quarantined', ['eq'], type: 'boolean', label: 'Quarantined')
            ->filterable('code', ['eq', 'starts'])
            ->includes('medication', 'supplier')
            ->column('code', 'Lot')
            ->column('medication', 'Medication')
            ->column('batch_number', 'Batch no.')
            ->column('expiry_date', 'Expiry')
            ->column('qty_on_hand', 'On hand')
            ->column('unit_cost', 'Unit cost')
            ->column('supplier', 'Supplier')
            ->dateFields('received_at', 'expiry_date')
            ->defaultSort('expiry_date');
    }

    public function isExpired(CarbonImmutable $today): bool
    {
        return $this->expiry_date->lt($today);
    }

    public function isDispensable(CarbonImmutable $today): bool
    {
        return ! $this->is_quarantined && ! $this->isExpired($today);
    }

    /**
     * Batches stock may be taken from today, first expiry first (FEFO).
     *
     * @param  Builder<MedicationBatch>  $query
     * @return Builder<MedicationBatch>
     */
    public function scopeDispensable(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->where('medication_batches.is_quarantined', false)
            ->where('medication_batches.is_deleted', false)
            ->whereDate('medication_batches.expiry_date', '>=', $today->toDateString());
    }

    /** @return BelongsTo<Medication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class)->withDeleted();
    }

    /** @return BelongsTo<Company, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id')->withDeleted();
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('medication_batches.created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'expiry_date' => 'immutable_date',
            'qty_received' => 'decimal:2',
            'qty_on_hand' => 'decimal:2',
            'unit_cost' => MoneyCast::class,
            'received_at' => 'immutable_datetime',
            'is_quarantined' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
