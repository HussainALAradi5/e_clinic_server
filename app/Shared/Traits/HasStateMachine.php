<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Exceptions\InvalidStateTransitionException;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Default implementation of the HasStateMachine contract using the model's static transitions() map.
 * Writes a status history row when the model defines statusHistories().
 *
 * @mixin Model
 */
trait HasStateMachine
{
    public function statusColumn(): string
    {
        return 'status';
    }

    public function canTransitionTo(BackedEnum $to): bool
    {
        $current = $this->getAttribute($this->statusColumn());
        $from = $current instanceof BackedEnum ? $current->value : $current;

        return in_array($to, static::transitions()[$from] ?? [], true);
    }

    public function transitionTo(BackedEnum $to, ?string $reason = null): void
    {
        $from = $this->getAttribute($this->statusColumn());

        if (! $this->canTransitionTo($to)) {
            throw new InvalidStateTransitionException(context: [
                'from' => $from instanceof BackedEnum ? $from->value : $from,
                'to' => $to->value,
            ]);
        }

        $this->setAttribute($this->statusColumn(), $to)->save();

        if (method_exists($this, 'statusHistories')) {
            $this->statusHistories()->create([
                'from_status' => $from instanceof BackedEnum ? $from->value : $from,
                'to_status' => $to->value,
                'reason' => $reason,
                'changed_by' => Auth::id(),
            ]);
        }
    }
}
