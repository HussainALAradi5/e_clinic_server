<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use BackedEnum;

/**
 * Aggregates whose status changes are guarded by a transitions map.
 */
interface HasStateMachine
{
    /**
     * @return array<string, list<BackedEnum>> from-status value => allowed target statuses
     */
    public static function transitions(): array;

    public function canTransitionTo(BackedEnum $to): bool;

    public function transitionTo(BackedEnum $to, ?string $reason = null): void;
}
