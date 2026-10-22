<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Appointment lifecycle; transitions() is the state machine (also published to the client by GET /meta/enums).
 */
enum AppointmentStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    /**
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [self::Confirmed, self::Cancelled, self::Rescheduled],
            self::Confirmed->value => [self::CheckedIn, self::Cancelled, self::NoShow, self::Rescheduled],
            self::CheckedIn->value => [self::InProgress],
            self::InProgress->value => [self::Completed],
            self::Completed->value => [],
            self::Cancelled->value => [],
            self::NoShow->value => [],
            self::Rescheduled->value => [],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::transitions()[$this->value], true);
    }

    /** Statuses that still hold the time slot (an active booking). */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::CheckedIn, self::InProgress, self::Completed], true);
    }

    /** @return list<string> */
    public static function slotHoldingValues(): array
    {
        return array_values(array_map(fn (self $s): string => $s->value, array_filter(self::cases(), fn (self $s): bool => $s->holdsSlot())));
    }
}
