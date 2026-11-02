<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Casts\MoneyCast;
use App\Enums\HR\PayslipItemType;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Payslip line: positive amount, earning or deduction by type.
 *
 * @property string $id
 * @property string $payslip_id
 * @property PayslipItemType $type
 * @property string $name
 * @property Money $amount
 * @property int $sort
 */
class PayslipItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => PayslipItemType::class,
            'amount' => MoneyCast::class,
            'sort' => 'integer',
        ];
    }
}
