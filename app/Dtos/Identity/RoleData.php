<?php

declare(strict_types=1);

namespace App\Dtos\Identity;

/**
 * Role labels and (optionally) its full permission list. `null` permissions = leave them unchanged.
 */
final readonly class RoleData
{
    /**
     * @param  list<string>|null  $permissions
     */
    public function __construct(
        public ?string $name = null,
        public ?string $displayName = null,
        public ?string $displayNameAr = null,
        public ?string $description = null,
        public ?array $permissions = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            name: isset($input['name']) ? (string) $input['name'] : null,
            displayName: isset($input['display_name']) ? (string) $input['display_name'] : null,
            displayNameAr: isset($input['display_name_ar']) ? (string) $input['display_name_ar'] : null,
            description: isset($input['description']) ? (string) $input['description'] : null,
            permissions: isset($input['permissions']) && is_array($input['permissions']) ? array_values(array_map('strval', $input['permissions'])) : null,
        );
    }
}
