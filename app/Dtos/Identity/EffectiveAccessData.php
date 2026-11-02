<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

/**
 * Merged (OR across roles) rights of the signed-in user, for Angular guards, menus and `*hasAccess`.
 */
final readonly class EffectiveAccessData
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public array $roles,
        public array $permissions,
    ) {}

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function toArray(): array
    {
        return ['roles' => $this->roles, 'permissions' => $this->permissions];
    }

    /**
     * @param  array{roles: list<string>, permissions: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['roles'], $data['permissions']);
    }
}
