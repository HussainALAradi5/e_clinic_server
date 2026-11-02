<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Accounting\FiscalStatus;
use App\Models\Base\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiscal year (default = calendar year) with its monthly periods.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property FiscalStatus $status
 * @property ?string $closing_journal_entry_id
 * @property ?string $closed_by
 * @property ?CarbonImmutable $closed_at
 * @property ?CarbonImmutable $created_at
 * @property-read Collection<int, FiscalPeriod> $periods
 */
class FiscalYear extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'FY';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    /**
     * @return HasMany<FiscalPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('period_number');
    }

    protected function casts(): array
    {
        return [
            'status' => FiscalStatus::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
