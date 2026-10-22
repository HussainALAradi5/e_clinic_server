<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Models;

use App\Enums\Pharmacy\StockMovementType;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only stock ledger row (quantities only; values live in the journal). Signed: + in, − out. `source` is the
 * receipt batch, the dispensation or the adjusted batch.
 *
 * @property string $id
 * @property string $medication_id
 * @property string $batch_id
 * @property StockMovementType $type
 * @property string $quantity
 * @property ?string $source_type
 * @property ?string $source_id
 * @property ?string $reason
 * @property ?string $approved_by
 * @property ?string $created_by
 * @property CarbonImmutable $created_at
 * @property-read Medication $medication
 * @property-read MedicationBatch $batch
 * @property-read ?Model $source
 * @property-read ?User $creator
 * @property-read ?User $approver
 */
class StockMovement extends Model implements OwnedByUser
{
    use HasListQuery;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('inventory')
            ->sortable('created_at')
            ->filterable('medication_id', ['eq', 'in'], resource: 'medications')
            ->filterable('batch_id', ['eq'])
            ->filterable('type', ['eq', 'in'], enum: StockMovementType::class)
            ->filterable('source_type', ['eq'])
            ->filterable('source_id', ['eq'])
            ->searchIn('reason')
            ->searchInRelation('medication', 'code', 'generic_name', 'brand_name')
            ->includes('medication', 'batch', 'creator.person')
            ->column('created_at', 'Time')
            ->column('medication', 'Medication')
            ->column('batch', 'Lot')
            ->column('type', 'Type')
            ->column('quantity', 'Quantity')
            ->column('reason', 'Reason')
            ->dateFields('created_at')
            ->cursorPagination()
            ->defaultSort('-created_at');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('stock_movements is append-only.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('stock_movements is append-only.');
    }

    /** @return BelongsTo<Medication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class)->withDeleted();
    }

    /** @return BelongsTo<MedicationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicationBatch::class)->withDeleted();
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        return $query->where('stock_movements.created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }
}
