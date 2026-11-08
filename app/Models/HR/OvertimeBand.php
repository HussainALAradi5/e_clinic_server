<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Casts\MoneyCast;
use App\Models\Base\BaseModel;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Overtime pay for one part of the day (OTB-000001), e.g. 17:00–19:00 at 2.500 per hour.
 *
 * @property string $id
 * @property string $code
 * @property int $start_minute
 * @property int $end_minute
 * @property Money $rate
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class OvertimeBand extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'OTB';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'start_minute' => 'integer',
            'end_minute' => 'integer',
            'rate' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
