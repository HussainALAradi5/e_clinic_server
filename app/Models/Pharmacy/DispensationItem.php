<?php

declare(strict_types=1);

namespace App\Models\Pharmacy;

use App\Casts\MoneyCast;
use App\Enums\Pharmacy\MedicationRoute;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One batch's share of a dispensed medicine, with the price (price list) and cost (batch) snapshots. Route and dose
 * are recorded for medicines given in a session.
 *
 * @property string $id
 * @property string $dispensation_id
 * @property ?string $prescription_item_id
 * @property string $medication_id
 * @property string $batch_id
 * @property string $quantity
 * @property Money $unit_price
 * @property Money $unit_cost
 * @property ?MedicationRoute $route
 * @property ?string $dose
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Dispensation $dispensation
 * @property-read ?PrescriptionItem $prescriptionItem
 * @property-read Medication $medication
 * @property-read MedicationBatch $batch
 */
class DispensationItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** @return BelongsTo<Dispensation, $this> */
    public function dispensation(): BelongsTo
    {
        return $this->belongsTo(Dispensation::class);
    }

    /** @return BelongsTo<PrescriptionItem, $this> */
    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
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

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'route' => MedicationRoute::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
