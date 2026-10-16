<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Single capability `<resource>.<flag|action>`, e.g. `invoices.read_all`, `invoices.issue`.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property string $module
 * @property ?string $description
 */
class Permission extends SpatiePermission
{
    use HasUuids;

    /** @var list<string> */
    public const FLAGS = ['read_own', 'read_all', 'create', 'write', 'delete'];

    /**
     * The five access-matrix flags.
     *
     * @return list<string>
     */
    public static function flags(): array
    {
        return self::FLAGS;
    }

    public function resource(): string
    {
        return explode('.', $this->name, 2)[0];
    }

    public function ability(): string
    {
        return explode('.', $this->name, 2)[1] ?? '';
    }
}
