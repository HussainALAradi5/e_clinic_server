<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Models;

use App\Enums\Pharmacy\MedicationRoute;
use App\Modules\Pharmacy\Dispensing\Models\DispensationItem;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Enums\GenericStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medicine on a prescription with its structured dosage. What was dispensed is computed (withDispensed) from the
 * completed dispensations — never stored.
 *
 * @property string $id
 * @property string $prescription_id
 * @property string $medication_id
 * @property string $dose
 * @property string $frequency
 * @property MedicationRoute $route
 * @property int $duration_days
 * @property string $quantity
 * @property ?string $instructions
 * @property-read string|int|float|null $dispensed_quantity
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Prescription $prescription
 * @property-read Medication $medication
 */
class PrescriptionItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** Prescribed minus dispensed (needs withDispensed). */
    public function remaining(): BigDecimal
    {
        $left = Qty::of($this->quantity)->minus(Qty::of($this->dispensed_quantity));

        return $left->isNegative() ? Qty::zero() : $left;
    }

    /**
     * Adds `dispensed_quantity` (sum over completed dispensations).
     *
     * @param  Builder<PrescriptionItem>  $query
     * @return Builder<PrescriptionItem>
     */
    public function scopeWithDispensed(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('prescription_items.*');
        }

        return $query->addSelect(['dispensed_quantity' => DispensationItem::query()
            ->selectRaw('COALESCE(SUM(dispensation_items.quantity), 0)')
            ->join('dispensations', 'dispensations.id', '=', 'dispensation_items.dispensation_id')
            ->whereColumn('dispensation_items.prescription_item_id', 'prescription_items.id')
            ->where('dispensations.status', GenericStatus::Completed->value)]);
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Medication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class)->withDeleted();
    }

    /** @return HasMany<DispensationItem, $this> */
    public function dispensationItems(): HasMany
    {
        return $this->hasMany(DispensationItem::class);
    }

    protected function casts(): array
    {
        return [
            'route' => MedicationRoute::class,
            'quantity' => 'decimal:2',
            'duration_days' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
