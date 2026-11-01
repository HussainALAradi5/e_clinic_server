<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * open → in progress → resolved → closed. Admins move it; a resolved ticket goes back to open when the requester
 * says it is not fixed (by replying), and closes on its own after a week.
 */
enum TicketStatus: string implements HasLabel
{
    use EnumHelpers;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Open->value => [self::InProgress, self::Resolved, self::Closed],
            self::InProgress->value => [self::Open, self::Resolved, self::Closed],
            self::Resolved->value => [self::Open, self::Closed],
            self::Closed->value => [],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::transitions()[$this->value], true);
    }

    public function isActive(): bool
    {
        return $this === self::Open || $this === self::InProgress;
    }
}
