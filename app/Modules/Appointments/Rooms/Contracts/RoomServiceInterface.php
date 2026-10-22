<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Rooms\Contracts;

use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Rooms\Data\RoomData;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Shared\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RoomServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Room>|CursorPaginator<int, Room>
     */
    public function listRooms(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findRoom(string $id): Room;

    public function findWithDeletedRoom(string $id): Room;

    public function createRoom(RoomData $data): Room;

    /** Changing the type is blocked while upcoming bookings need the old one. */
    public function updateRoom(Room $room, RoomData $data): Room;

    /**
     * Takes a room out of / back into service. Out of service keeps bookings: they are returned for reassignment.
     *
     * @return array{room: Room, affected: Collection<int, Appointment>}
     */
    public function setInService(Room $room, bool $inService, ?string $reason = null): array;

    /**
     * Bookings holding the room on a date (busy intervals).
     *
     * @return Collection<int, Appointment>
     */
    public function availability(Room $room, CarbonImmutable $date): Collection;

    /**
     * Upcoming active bookings in the room (today onward).
     *
     * @return Collection<int, Appointment>
     */
    public function upcomingBookings(Room $room): Collection;

    /** Soft delete; upcoming bookings → RoomInUseException. */
    public function deleteRoom(Room $room): void;

    /** Upcoming bookings → RoomInUseException. */
    public function archiveRoom(Room $room): Room;

    public function unarchiveRoom(Room $room): Room;

    public function restoreRoom(Room $room): Room;
}
