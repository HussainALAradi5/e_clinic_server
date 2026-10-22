<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Models;

use App\Enums\Accounting\FiscalStatus;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly posting period (13 = year-end adjustment period, dated on the last day of the year).
 *
 * @property string $id
 * @property string $code
 * @property string $fiscal_year_id
 * @property int $period_number
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property FiscalStatus $status
 * @property ?string $closed_by
 * @property ?CarbonImmutable $closed_at
 * @property-read FiscalYear $fiscalYear
 */
class FiscalPeriod extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'FP';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public function isOpen(): bool
    {
        return $this->status === FiscalStatus::Open;
    }

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    protected function casts(): array
    {
        return [
            'status' => FiscalStatus::class,
            'period_number' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
