<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Models;

use App\Enums\Pharmacy\DispenseUnit;
use App\Enums\Pharmacy\MedicationForm;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Medication of the clinic pharmacy. Its price, VAT and revenue account are those of the linked price-list service
 * (one price list); `stock_on_hand` (withStock) is the sum of its dispensable batches.
 *
 * @property string $id
 * @property string $code
 * @property string $generic_name
 * @property ?string $brand_name
 * @property ?string $name_ar
 * @property MedicationForm $form
 * @property string $strength
 * @property DispenseUnit $unit
 * @property ?string $gtin
 * @property ?string $manufacturer_id
 * @property ?string $origin_country_code
 * @property ?string $description
 * @property string $billable_service_id
 * @property bool $requires_prescription
 * @property bool $is_controlled
 * @property int $reorder_level
 * @property ?string $storage_notes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?string $stock_on_hand
 * @property-read ?Company $manufacturer
 * @property-read ?Country $originCountry
 * @property-read BillableService $billableService
 * @property-read Collection<int, MedicationBatch> $batches
 */
class Medication extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'MED';
    }

    /** Pharmacists enter readable codes, e.g. `MED-AMOX-500` (also the price-list service code). */
    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('medications')
            ->sortable('code', 'generic_name', 'brand_name', 'form', 'stock_on_hand', 'reorder_level', 'created_at')
            ->filterable('form', ['eq', 'in'], enum: MedicationForm::class)
            ->filterable('unit', ['eq', 'in'], enum: DispenseUnit::class)
            ->filterable('manufacturer_id', ['eq', 'in', 'null'], resource: 'companies')
            ->filterable('origin_country_code', ['eq', 'in'], label: 'Country of origin')
            ->filterable('requires_prescription', ['eq'], type: 'boolean', label: 'Prescription only')
            ->filterable('is_controlled', ['eq'], type: 'boolean', label: 'Controlled drug')
            ->filterable('gtin', ['eq'], label: 'Barcode (GTIN)')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->searchIn('code', 'generic_name', 'brand_name', 'name_ar', 'gtin')
            ->includes('manufacturer', 'originCountry', 'billableService')
            ->column('code', 'Code')
            ->column('generic_name', 'Generic name')
            ->column('brand_name', 'Brand')
            ->column('strength', 'Strength')
            ->column('form', 'Form')
            ->column('price', 'Price')
            ->column('stock_on_hand', 'In stock')
            ->column('reorder_level', 'Reorder level')
            ->dateFields('created_at')
            ->defaultSort('generic_name');
    }

    /** e.g. `Amoxicillin 500 mg capsule (Amoxil)` — invoice lines, labels and pickers. */
    public function displayName(): string
    {
        $name = trim("{$this->generic_name} {$this->strength} {$this->form->label()}");

        return $this->brand_name === null ? $name : "{$name} ({$this->brand_name})";
    }

    /**
     * Adds `stock_on_hand`: quantity of the batches that may be dispensed today (not expired, not quarantined).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function addStock(Builder $query, CarbonImmutable $today): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('medications.*');
        }

        return $query->addSelect(['stock_on_hand' => MedicationBatch::query()
            ->selectRaw('COALESCE(SUM(qty_on_hand), 0)')
            ->whereColumn('medication_batches.medication_id', 'medications.id')
            ->dispensable($today)]);
    }

    /**
     * @param  Builder<Medication>  $query
     * @return Builder<Medication>
     */
    public function scopeWithStock(Builder $query, CarbonImmutable $today): Builder
    {
        return self::addStock($query, $today);
    }

    /** @return BelongsTo<Company, $this> */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'manufacturer_id')->withDeleted();
    }

    /** @return BelongsTo<Country, $this> */
    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_code', 'code');
    }

    /** @return BelongsTo<BillableService, $this> */
    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class)->withDeleted();
    }

    /** @return HasMany<MedicationBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(MedicationBatch::class);
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
        return $query->where('medications.created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'form' => MedicationForm::class,
            'unit' => DispenseUnit::class,
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'reorder_level' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
