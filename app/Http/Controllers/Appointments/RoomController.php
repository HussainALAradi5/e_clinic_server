<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\RoomRequest;
use App\Http\Resources\Appointments\AppointmentResource;
use App\Http\Resources\Appointments\RoomResource;
use App\Interfaces\Appointments\RoomServiceInterface;
use App\Models\Appointments\Room;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RoomController
{
    use ApiResponse;

    public function __construct(private readonly RoomServiceInterface $rooms) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Room::listQueryConfig());

        return $this->paginated(RoomResource::collection($this->rooms->listRooms($query)), $query);
    }

    public function show(string $room): JsonResponse
    {
        return $this->ok(new RoomResource($this->rooms->findRoom($room)));
    }

    public function availability(Request $request, string $room): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);
        $bookings = $this->rooms->availability($this->rooms->findRoom($room), CarbonImmutable::parse($request->string('date')->toString())->startOfDay());

        return $this->ok(AppointmentResource::collection($bookings));
    }

    public function store(RoomRequest $request): JsonResponse
    {
        return $this->created(new RoomResource($this->rooms->createRoom($request->toData())));
    }

    public function update(RoomRequest $request, string $room): JsonResponse
    {
        return $this->ok(new RoomResource($this->rooms->updateRoom($this->rooms->findRoom($room), $request->toData())));
    }

    /** Out of service keeps the bookings: they come back as `affected` so the desk can move them. */
    public function service(Request $request, string $room): JsonResponse
    {
        $request->validate([
            'in_service' => ['required', 'boolean'],
            'reason' => ['required_if:in_service,false', 'nullable', 'string', 'max:255'],
        ]);
        $result = $this->rooms->setInService($this->rooms->findRoom($room), $request->boolean('in_service'), $request->filled('reason') ? $request->string('reason')->toString() : null);

        return $this->ok([
            ...(new RoomResource($result['room']))->resolve($request),
            'affected_appointments' => AppointmentResource::collection($result['affected'])->resolve($request),
        ]);
    }

    public function destroy(string $room): Response
    {
        $this->rooms->deleteRoom($this->rooms->findRoom($room));

        return $this->noContent();
    }

    public function archive(string $room): JsonResponse
    {
        return $this->ok(new RoomResource($this->rooms->archiveRoom($this->rooms->findRoom($room))));
    }

    public function unarchive(string $room): JsonResponse
    {
        return $this->ok(new RoomResource($this->rooms->unarchiveRoom($this->rooms->findRoom($room))));
    }

    public function restore(string $room): JsonResponse
    {
        return $this->ok(new RoomResource($this->rooms->restoreRoom($this->rooms->findWithDeletedRoom($room))));
    }
}
