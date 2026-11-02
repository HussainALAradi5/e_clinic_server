<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Priority shared by treatments, clinical orders and support tickets: medium · high · urgent.
 * For treatments: urgent → first session within 24 h, high → 72 h.
 */
enum TreatmentPriority: string implements HasLabel
{
    use EnumHelpers;

    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /** Hours within which the first session must take place (null = no deadline). */
    public function deadlineHours(): ?int
    {
        return match ($this) {
            self::Urgent => 24,
            self::High => 72,
            self::Medium => null,
        };
    }

    /** Sort weight: urgent first. */
    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 3,
            self::High => 2,
            self::Medium => 1,
        };
    }
}
