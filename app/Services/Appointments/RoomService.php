<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Dtos\Appointments\RoomData;
use App\Enums\Appointments\AppointmentStatus;
use App\Exceptions\Appointments\RoomInUseException;
use App\Interfaces\Appointments\RoomServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Room;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\GenericEntityScopes;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<Room>
 */
final class RoomService extends BaseEntityService implements RoomServiceInterface
{
    public function __construct(ApplyListQuery $listQuery, private readonly ClinicClock $clock)
    {
        parent::__construct($listQuery);
    }

    public function listRooms(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findRoom(string $id): Room
    {
        return $this->find($id);
    }

    public function findWithDeletedRoom(string $id): Room
    {
        return $this->findWithDeleted($id);
    }

    public function createRoom(RoomData $data): Room
    {
        $this->assertCodeFree((string) $data->values['code']);

        return $this->findRoom(Room::query()->create($data->values)->id);
    }

    public function updateRoom(Room $room, RoomData $data): Room
    {
        return DB::transaction(function () use ($room, $data): Room {
            $room->fill($data->values);
            if ($room->isDirty('code')) {
                $this->assertCodeFree($room->code, $room->id);
            }
            if ($room->isDirty('type')) {
                $needOldType = $this->upcomingBookings($room)->reject(fn (Appointment $a): bool => in_array($room->type, Room::typesFor($a->type), true));
                if ($needOldType->isNotEmpty()) {
                    throw new RoomInUseException(context: ['appointments' => $needOldType->pluck('code')->all()]);
                }
            }
            $room->save();

            return $this->findRoom($room->id);
        });
    }

    public function setInService(Room $room, bool $inService, ?string $reason = null): array
    {
        $room->forceFill(['is_in_service' => $inService, 'out_of_service_reason' => $inService ? null : $reason])->save();

        return ['room' => $room, 'affected' => $inService ? collect() : $this->upcomingBookings($room)];
    }

    public function availability(Room $room, CarbonImmutable $date): Collection
    {
        return Appointment::query()
            ->where('room_id', $room->id)
            ->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->with(['patient.person', 'provider.person'])
            ->orderBy('start_time')
            ->get();
    }

    public function upcomingBookings(Room $room): Collection
    {
        $now = $this->clock->toClinic($this->clock->now());

        return Appointment::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value, AppointmentStatus::InProgress->value])
            ->where(fn (Builder $q) => $q->whereDate('scheduled_date', '>', $now->toDateString())
                ->orWhere(fn (Builder $q) => $q->whereDate('scheduled_date', $now->toDateString())->where('end_time', '>', $now->format('H:i:s'))))
            ->with(['patient.person', 'provider.person'])
            ->orderBy('scheduled_date')->orderBy('start_time')
            ->get();
    }

    public function deleteRoom(Room $room): void
    {
        $this->delete($room);
    }

    public function archiveRoom(Room $room): Room
    {
        return $room->isArchived() ? $room : $this->archive($room);
    }

    public function unarchiveRoom(Room $room): Room
    {
        return $room->isArchived() ? $this->unarchive($room) : $room;
    }

    public function restoreRoom(Room $room): Room
    {
        return $room->isDeleted() ? $this->restore($room) : $room;
    }

    /** Also used by bulk archive: rooms with upcoming bookings stay. */
    public function archive(Model $model): Model
    {
        /** @var Room $model */
        $this->assertNoUpcomingBookings($model);

        return parent::archive($model);
    }

    public function restore(Model $model): Model
    {
        /** @var Room $model */
        $this->assertCodeFree($model->code, $model->id);

        return parent::restore($model);
    }

    /**
     * @return Builder<Room>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Room::query()->with('department'), 'rooms', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Room $model */
        $this->assertNoUpcomingBookings($model);
    }

    private function assertNoUpcomingBookings(Room $room): void
    {
        $upcoming = $this->upcomingBookings($room);
        if ($upcoming->isNotEmpty()) {
            throw new RoomInUseException(context: ['appointments' => $upcoming->pluck('code')->all()]);
        }
    }

    private function assertCodeFree(string $code, ?string $exceptId = null): void
    {
        $taken = GenericEntityScopes::withDeleted(Room::query())->where('code', $code)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['code' => [__('validation.unique', ['attribute' => 'code'])]]);
        }
    }
}
