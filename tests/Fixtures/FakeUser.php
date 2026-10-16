<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * In-memory user holding a flat permission list (no database).
 */
final class FakeUser extends GenericUser implements Authorizable
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(private readonly array $permissions = [], string $id = 'user-1')
    {
        parent::__construct(['id' => $id]);
    }

    /**
     * @param  iterable<string>|string  $abilities
     * @param  array<mixed>|mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        return collect(is_iterable($abilities) ? $abilities : [$abilities])
            ->every(fn (string $ability): bool => in_array($ability, $this->permissions, true));
    }
}
