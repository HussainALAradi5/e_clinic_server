<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Base\BaseModel;
use Carbon\CarbonImmutable;

/**
 * Configurable leave type (annual, sick, emergency, unpaid …). A quota of 0 on a paid type means no yearly limit;
 * unpaid types are never limited and are deducted in payroll.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $name_ar
 * @property string $annual_quota_days
 * @property bool $is_paid
 * @property bool $requires_attachment
 * @property string $max_carry_forward_days
 * @property int $min_notice_days
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class LeaveType extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'LT';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    /** Paid types with a yearly quota are limited by the balance. */
    public function isLimited(): bool
    {
        return $this->is_paid && (float) $this->annual_quota_days > 0;
    }

    /** Sick and emergency leave cannot be planned, so no notice is asked for them. */
    public function needsNotice(): bool
    {
        return $this->min_notice_days > 0;
    }

    protected function casts(): array
    {
        return [
            'annual_quota_days' => 'decimal:1',
            'max_carry_forward_days' => 'decimal:1',
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'min_notice_days' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
