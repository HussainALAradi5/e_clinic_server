<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Lifecycle shared by every simple process (treatments, prescriptions, clinical orders, background tasks).
 */
enum GenericStatus: string implements HasLabel
{
    use EnumHelpers;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [self::InProgress, self::Cancelled],
            self::InProgress->value => [self::Completed, self::Cancelled],
            self::Completed->value => [],
            self::Cancelled->value => [],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::transitions()[$this->value], true);
    }

    public function isFinal(): bool
    {
        return self::transitions()[$this->value] === [];
    }
}
