<?php

declare(strict_types=1);

namespace App\Traits\Shared;

use App\Enums\Shared\GenericStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * GenericStatus lifecycle (pending → in_progress → completed, pending|in_progress → cancelled) with scopes.
 *
 * @mixin Model
 */
trait HasGenericStatus
{
    use HasStateMachine;

    public function initializeHasGenericStatus(): void
    {
        $this->mergeCasts([$this->statusColumn() => GenericStatus::class]);
        $this->attributes += [$this->statusColumn() => GenericStatus::Pending->value];
    }

    /**
     * @return array<string, list<GenericStatus>>
     */
    public static function transitions(): array
    {
        return GenericStatus::transitions();
    }

    /** Column that stores the cancellation reason; null when the model has none. */
    public function cancellationReasonColumn(): ?string
    {
        return 'cancellation_reason';
    }

    public function start(): static
    {
        $this->transitionTo(GenericStatus::InProgress);

        return $this;
    }

    public function complete(): static
    {
        $this->transitionTo(GenericStatus::Completed);

        return $this;
    }

    public function cancel(?string $reason = null): static
    {
        $column = $this->cancellationReasonColumn();
        if ($reason !== null && $column !== null) {
            $this->setAttribute($column, $reason);
        }

        $this->transitionTo(GenericStatus::Cancelled, $reason);

        return $this;
    }

    public function isFinal(): bool
    {
        return $this->getAttribute($this->statusColumn())->isFinal();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithStatus(Builder $query, GenericStatus ...$statuses): Builder
    {
        return $query->whereIn($query->qualifyColumn($this->statusColumn()), array_map(fn (GenericStatus $s): string => $s->value, $statuses));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, GenericStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, GenericStatus::InProgress);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, GenericStatus::Completed);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, GenericStatus::Cancelled);
    }

    /**
     * Pending + in progress.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, GenericStatus::Pending, GenericStatus::InProgress);
    }
}
