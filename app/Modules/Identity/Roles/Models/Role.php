<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Models;

use App\Enums\Identity\RoleName;
use App\Shared\Contracts\Auditable;
use App\Shared\Contracts\GenericEntity;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\HasCode;
use App\Shared\Traits\HasGenericEntity;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Named group of permissions (GenericEntity: code, archive, soft delete). System roles are seeded and protected.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $guard_name
 * @property string $display_name
 * @property ?string $display_name_ar
 * @property ?string $description
 * @property bool $is_system
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $users_count
 * @property-read int|null $permissions_count
 */
class Role extends SpatieRole implements Auditable, GenericEntity
{
    use AuditsChanges;
    use HasCode;
    use HasGenericEntity;
    use HasListQuery;
    use HasUuids;

    protected $guarded = ['id', 'is_archived', 'is_deleted', 'deleted_at', 'deleted_by'];

    public static function codePrefix(): string
    {
        return 'ROL';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('roles')
            ->sortable('code', 'name', 'display_name', 'created_at', 'users_count', 'permissions_count')
            ->filterable('is_system', ['eq'], type: 'boolean')
            ->searchIn('name', 'display_name', 'display_name_ar', 'code')
            ->column('code', 'Code')
            ->column('display_name', 'Role')
            ->column('users_count', 'Users')
            ->column('permissions_count', 'Permissions')
            ->dateFields('created_at')
            ->defaultSort('display_name');
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === RoleName::SuperAdmin->value;
    }

    public function delete(): bool
    {
        $this->softDelete();

        return true;
    }

    public function forceDelete(): never
    {
        throw new LogicException('Physical deletes are not allowed (soft delete only).');
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
