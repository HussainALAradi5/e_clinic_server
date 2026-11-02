<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Dtos\Appointments\CancelAppointmentData;
use App\Dtos\Appointments\SlotData;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\CancelledBy;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\PortalBookingRequest;
use App\Http\Requests\Appointments\PortalProfileRequest;
use App\Http\Resources\Appointments\AppointmentResource;
use App\Http\Resources\Appointments\PatientResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Models\HR\Employee;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * Patient portal (`/portal/*`, patients only): the few things a patient changes themselves. Everything they read
 * (appointments, treatments, results, prescriptions, invoices) comes from the regular endpoints, which return
 * only their own records.
 */
final class PortalController
{
    use ApiResponse;

    public function __construct(
        private readonly PatientServiceInterface $patients,
        private readonly AppointmentServiceInterface $appointments,
        private readonly ClinicClock $clock,
    ) {}

    public function profile(Request $request): JsonResponse
    {
        return $this->ok(new PatientResource($this->patients->find($this->patientId($request))));
    }

    public function updateProfile(PortalProfileRequest $request): JsonResponse
    {
        $patient = $this->patients->find($this->patientId($request));

        return $this->ok(new PatientResource($this->patients->updateContact(
            $patient,
            $request->person(),
            $request->integer('person_version'),
            $request->patient(),
            $request->integer('version'),
        )));
    }

    /** Free slots of a doctor that can still be booked online (at least the portal lead time ahead). */
    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'uuid'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'type' => ['required', Rule::enum(AppointmentType::class)->only(PortalBookingRequest::TYPES)],
        ]);
        $earliest = $this->clock->now()->addHours(Config::integer('appointments.portal_min_hours_ahead'));
        $slots = $this->appointments->getAvailableSlots(
            Employee::query()->with('user')->findOrFail($request->string('doctor_id')->toString()),
            CarbonImmutable::parse($request->string('date')->toString())->startOfDay(),
            AppointmentType::from($request->string('type')->toString()),
        );

        return $this->ok($slots->filter(fn (SlotData $s): bool => $s->start->gte($earliest))->map(fn (SlotData $s): array => $s->toArray())->values()->all());
    }

    /** Books for the signed-in patient; the booking waits for the clinic to confirm it. */
    public function book(PortalBookingRequest $request): JsonResponse
    {
        return $this->created(new AppointmentResource($this->appointments->book($request->toData($this->patientId($request)))));
    }

    /** Own booking only (others are 404); refused inside the change window before the start. */
    public function cancel(Request $request, string $appointment): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new AppointmentResource($this->appointments->cancel(
            $this->appointments->find($appointment),
            new CancelAppointmentData(CancelledBy::Patient, $request->string('reason')->toString()),
        )));
    }

    private function patientId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
