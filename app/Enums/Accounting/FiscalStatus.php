<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Fiscal year / period status: postings only into open periods; locked is final.
 */
enum FiscalStatus: string implements HasLabel
{
    use EnumHelpers;

    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';

    /**
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Open->value => [self::Closed],
            self::Closed->value => [self::Open, self::Locked],
            self::Locked->value => [],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::transitions()[$this->value], true);
    }
}
