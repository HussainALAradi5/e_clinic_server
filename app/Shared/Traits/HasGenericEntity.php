<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Support\GenericEntityScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * GenericEntity behaviour: archive via `is_archived`, soft delete via `is_deleted` (+ deleted_at / deleted_by),
 * and a global scope that hides deleted rows. Fires `archived`, `unarchived`, `softDeleted` and `restored` model events.
 *
 * @mixin Model
 */
trait HasGenericEntity
{
    public static function bootHasGenericEntity(): void
    {
        static::addGlobalScope(GenericEntityScopes::NOT_DELETED, function (Builder $builder): void {
            $builder->where($builder->qualifyColumn('is_deleted'), false);
        });
    }

    public function initializeHasGenericEntity(): void
    {
        $this->mergeCasts([
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ]);
        $this->addObservableEvents(['archived', 'unarchived', 'softDeleted', 'restored']);
        $this->attributes += ['is_archived' => false, 'is_deleted' => false];
    }

    public function getId(): string
    {
        return (string) $this->getKey();
    }

    public function getCode(): ?string
    {
        return $this->getAttribute('code');
    }

    public function isArchived(): bool
    {
        return (bool) $this->getAttribute('is_archived');
    }

    public function isDeleted(): bool
    {
        return (bool) $this->getAttribute('is_deleted');
    }

    public function archive(): static
    {
        return $this->changeFlags(['is_archived' => true], 'archived');
    }

    public function unarchive(): static
    {
        return $this->changeFlags(['is_archived' => false], 'unarchived');
    }

    public function softDelete(): static
    {
        return $this->changeFlags(['is_deleted' => true, 'deleted_at' => now(), 'deleted_by' => Auth::id()], 'softDeleted');
    }

    public function restore(): static
    {
        return $this->changeFlags(['is_deleted' => false, 'deleted_at' => null, 'deleted_by' => null], 'restored');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return GenericEntityScopes::notArchived($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDeleted(Builder $query): Builder
    {
        return GenericEntityScopes::withDeleted($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOnlyDeleted(Builder $query): Builder
    {
        return GenericEntityScopes::onlyDeleted($query);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function changeFlags(array $attributes, string $event): static
    {
        $this->forceFill($attributes)->save();
        $this->fireModelEvent($event, false);

        return $this;
    }
}
