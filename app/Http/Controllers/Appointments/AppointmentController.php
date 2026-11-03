<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Dtos\Appointments\CalendarQueryData;
use App\Dtos\Appointments\SlotData;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\VisitTiming;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\BookAppointmentRequest;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\CompleteAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Http\Resources\Appointments\AppointmentResource;
use App\Http\Resources\Identity\AuditLogResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\HR\Employee;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AppointmentController
{
    use ApiResponse;

    public function __construct(private readonly AppointmentServiceInterface $appointments) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['timing' => ['nullable', Rule::enum(VisitTiming::class)]]);
        $query = ListQuery::fromRequest($request, Appointment::listQueryConfig());
        $timing = $request->filled('timing') ? VisitTiming::from($request->string('timing')->toString()) : null;

        return $this->paginated(AppointmentResource::collection($this->appointments->list($query, $timing)), $query);
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.CarbonImmutable::parse((string) $request->input('from', 'today'))->addDays(31)->toDateString()],
            'provider_id' => ['nullable', 'uuid'],
            'room_id' => ['nullable', 'uuid'],
        ]);

        return $this->ok(AppointmentResource::collection($this->appointments->calendar(new CalendarQueryData(
            CarbonImmutable::parse($request->string('from')->toString())->startOfDay(),
            CarbonImmutable::parse($request->string('to')->toString())->startOfDay(),
            $request->filled('provider_id') ? $request->string('provider_id')->toString() : null,
            $request->filled('room_id') ? $request->string('room_id')->toString() : null,
        ))));
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id' => ['required', 'uuid'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'room_id' => ['nullable', 'uuid'],
        ]);
        $slots = $this->appointments->getAvailableSlots(
            Employee::query()->with('user')->findOrFail($request->string('provider_id')->toString()),
            CarbonImmutable::parse($request->string('date')->toString())->startOfDay(),
            AppointmentType::from($request->string('type')->toString()),
            $request->filled('room_id') ? $request->string('room_id')->toString() : null,
        );

        return $this->ok($slots->map(fn (SlotData $s): array => $s->toArray())->all());
    }

    /** Who can be booked for a kind of visit (doctors; medical staff too for treatment sessions). */
    public function providers(Request $request): JsonResponse
    {
        $request->validate(['type' => ['required', Rule::enum(AppointmentType::class)]]);
        $providers = $this->appointments->providers(AppointmentType::from($request->string('type')->toString()));

        return $this->ok($providers->map(fn (Employee $e): array => [
            'id' => $e->id,
            'full_name' => $e->person->full_name,
            'job_title' => $e->job_title,
            'department' => $e->department->name,
            'is_doctor' => $e->relationLoaded('doctor'),
        ])->all());
    }

    public function show(string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->find($appointment)));
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        return $this->created(new AppointmentResource($this->appointments->book($request->toData())));
    }

    public function confirm(string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->confirm($this->appointments->find($appointment))));
    }

    public function checkIn(string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->checkIn($this->appointments->find($appointment))));
    }

    public function start(string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->start($this->appointments->find($appointment))));
    }

    public function complete(CompleteAppointmentRequest $request, string $appointment): JsonResponse
    {
        $data = $request->toData();
        $completed = $this->appointments->complete($this->appointments->find($appointment), $data);

        return $this->ok([
            ...(new AppointmentResource($completed))->resolve($request),
            'follow_up_suggested_date' => $data->followUpInDays !== null ? $completed->scheduled_date->addDays($data->followUpInDays)->toDateString() : null,
        ]);
    }

    public function cancel(CancelAppointmentRequest $request, string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->cancel($this->appointments->find($appointment), $request->toData())));
    }

    public function reschedule(RescheduleAppointmentRequest $request, string $appointment): JsonResponse
    {
        return $this->created(new AppointmentResource($this->appointments->reschedule($this->appointments->find($appointment), $request->toData())));
    }

    public function noShow(string $appointment): JsonResponse
    {
        return $this->ok(new AppointmentResource($this->appointments->markNoShow($this->appointments->find($appointment))));
    }

    public function history(string $appointment): JsonResponse
    {
        return $this->ok(AuditLogResource::collection($this->appointments->history($this->appointments->find($appointment))));
    }
}
