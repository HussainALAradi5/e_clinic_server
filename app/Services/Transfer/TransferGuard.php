<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Dtos\Bulk\BulkResourceDefinition;
use App\Exceptions\Shared\UnknownResourceException;
use App\Exceptions\Transfer\TransferNotAllowedException;
use App\Services\Bulk\BulkActionRegistry;
use App\Utilities\Access\PermissionExpression;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may export / import a list: it must be offered for the resource, the user must read the list and hold
 * `<resource>.export` / `<resource>.import` — and patients never may, whatever their roles grant.
 */
final class TransferGuard
{
    public function __construct(private readonly BulkActionRegistry $registry) {}

    public function forExport(string $resource, Authenticatable $user): BulkResourceDefinition
    {
        $definition = $this->readable($resource, $user);

        if (! $this->canExport($definition, $user)) {
            throw new TransferNotAllowedException;
        }

        return $definition;
    }

    public function forImport(string $resource, Authenticatable $user): BulkResourceDefinition
    {
        $definition = $this->readable($resource, $user);

        if (! $this->canImport($definition, $user)) {
            throw new TransferNotAllowedException;
        }

        return $definition;
    }

    public function canExport(BulkResourceDefinition $definition, Authenticatable $user): bool
    {
        return $definition->exportResource() !== null && ! $this->isPatient($user)
            && PermissionExpression::parse($definition->exportPermission())->evaluate($user);
    }

    public function canImport(BulkResourceDefinition $definition, Authenticatable $user): bool
    {
        return $definition->importDefinition() !== null && ! $this->isPatient($user)
            && PermissionExpression::parse($definition->importPermission())->evaluate($user);
    }

    private function readable(string $resource, Authenticatable $user): BulkResourceDefinition
    {
        if (! $this->registry->has($resource)) {
            throw new UnknownResourceException;
        }

        $definition = $this->registry->for($resource);
        if (! PermissionExpression::parse($definition->readPermission())->evaluate($user)) {
            throw new UnknownResourceException;
        }

        return $definition;
    }

    private function isPatient(Authenticatable $user): bool
    {
        $type = $user->getAttribute('user_type') ?? null;

        return $type instanceof \BackedEnum ? $type->value === 'patient' : $type === 'patient';
    }
}
