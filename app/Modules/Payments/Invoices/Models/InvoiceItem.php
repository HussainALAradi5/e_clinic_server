<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Models;

use App\Enums\Payments\DiscountType;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Shared\Casts\MoneyCast;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Invoice line. Price, VAT rate and revenue account are snapshots taken when the line was added;
 * `chargeable` is the visit / order / dispensation it bills (null = manual line).
 *
 * @property string $id
 * @property string $invoice_id
 * @property ?string $billable_service_id
 * @property ?string $chargeable_type
 * @property ?string $chargeable_id
 * @property ?string $doctor_id
 * @property string $description
 * @property string $quantity
 * @property Money $unit_price
 * @property ?DiscountType $discount_type
 * @property string $discount_value
 * @property Money $discount_amount
 * @property string $tax_rate
 * @property Money $tax_amount
 * @property Money $line_total
 * @property string $revenue_account_id
 * @property ?CarbonImmutable $created_at
 * @property-read Invoice $invoice
 * @property-read ?BillableService $billableService
 * @property-read Account $revenueAccount
 * @property-read ?Doctor $doctor
 * @property-read ?Model $chargeable
 */
class InvoiceItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** Net revenue of the line (before VAT). */
    public function net(): Money
    {
        return $this->line_total->minus($this->tax_amount);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<BillableService, $this>
     */
    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class)->withDeleted();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id')->withDeleted();
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function chargeable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'quantity' => 'decimal:2',
            'discount_value' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'unit_price' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
