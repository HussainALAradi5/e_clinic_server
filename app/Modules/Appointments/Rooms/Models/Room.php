<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Rooms\Models;

use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\RoomType;
use App\Modules\HR\Departments\Models\Department;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical room (code entered by staff, e.g. `R-101`).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $name_ar
 * @property RoomType $type
 * @property ?string $department_id
 * @property ?string $floor
 * @property int $capacity
 * @property bool $is_in_service
 * @property ?string $out_of_service_reason
 * @property ?string $notes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Department $department
 */
class Room extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    /** BR-02-ROOM-03: room types each kind of booking may use. */
    private const BOOKING_ROOM_TYPES = [
        'visit' => [RoomType::Consultation, RoomType::Physical],
        'session' => [RoomType::SessionRoom, RoomType::TreatmentRoom, RoomType::Physical],
    ];

    public static function codePrefix(): string
    {
        return 'ROOM';
    }

    public static function usesManualCode(): bool
    {
        return true;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('rooms')
            ->sortable('code', 'name', 'type', 'floor', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: RoomType::class)
            ->filterable('department_id', ['eq', 'in', 'null'], resource: 'departments')
            ->filterable('is_in_service', ['eq'], type: 'boolean')
            ->filterable('code', ['eq', 'in', 'starts'])
            ->searchIn('code', 'name', 'name_ar', 'floor', 'notes')
            ->includes('department')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('type', 'Type')
            ->column('department', 'Department')
            ->column('floor', 'Floor')
            ->column('capacity', 'Capacity')
            ->column('is_in_service', 'Available')
            ->dateFields('created_at')
            ->defaultSort('code');
    }

    /** @return list<RoomType> */
    public static function typesFor(AppointmentType $type): array
    {
        return self::BOOKING_ROOM_TYPES[$type === AppointmentType::TreatmentSession ? 'session' : 'visit'];
    }

    public function isBookable(): bool
    {
        return $this->is_in_service && ! $this->is_archived && ! $this->is_deleted;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withDeleted();
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'capacity' => 'integer',
            'is_in_service' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
