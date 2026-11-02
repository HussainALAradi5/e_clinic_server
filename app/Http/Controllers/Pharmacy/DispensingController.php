<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pharmacy;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Pharmacy\DispenseRequest;
use App\Http\Resources\Pharmacy\DispensationResource;
use App\Http\Resources\Pharmacy\PrescriptionResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Pharmacy\DispensingServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\HR\Employee;
use App\Models\Identity\Person;
use App\Models\Pharmacy\Dispensation;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\Recipients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DispensingController
{
    use ApiResponse;

    public function __construct(
        private readonly DispensingServiceInterface $dispensing,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Dispensation::listQueryConfig());

        return $this->paginated(DispensationResource::collection($this->dispensing->list($query)), $query);
    }

    public function show(string $dispensation): JsonResponse
    {
        return $this->ok(new DispensationResource($this->dispensing->find($dispensation)));
    }

    public function store(DispenseRequest $request): JsonResponse
    {
        return $this->created(new DispensationResource($this->dispensing->dispense($request->toDispenseData())));
    }

    public function administer(DispenseRequest $request, string $appointment): JsonResponse
    {
        return $this->created(new DispensationResource($this->dispensing->administer($this->appointments->find($appointment), $request->toAdministerData())));
    }

    /** Pharmacy staff see any session's medicines; clinicians only those of appointments they may open. */
    public function forAppointment(Request $request, string $appointment): JsonResponse
    {
        $session = $request->user()?->can('dispensing.read_all') === true
            ? Appointment::query()->findOrFail($appointment)
            : $this->appointments->find($appointment);
        $medicines = $this->dispensing->forAppointment($session);

        return $this->ok([
            'prescriptions' => PrescriptionResource::collection($medicines->prescriptions),
            'dispensations' => DispensationResource::collection($medicines->dispensations),
        ]);
    }

    /**
     * Staff who may witness a controlled drug or approve a stock correction (never the caller).
     */
    public function colleagues(Request $request): JsonResponse
    {
        $right = $request->validate(['right' => ['required', Rule::in(['dispense', 'administer', 'adjust'])]])['right'];
        $permission = $right === 'adjust' ? 'inventory.adjust' : "dispensing.{$right}";
        $candidates = Recipients::withPermission($permission, [(string) $request->user()?->getAuthIdentifier()]);
        $staff = Employee::query()->whereKey($candidates->pluck('id'))->pluck('id')->flip();
        $people = $candidates
            ->filter(fn (Person $p): bool => $staff->has($p->id))
            ->sortBy('full_name')
            ->map(fn (Person $p): array => ['id' => $p->id, 'full_name' => $p->full_name])
            ->values();

        return $this->ok($people);
    }

    public function cancel(Request $request, string $dispensation): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new DispensationResource($this->dispensing->cancel($this->dispensing->find($dispensation), $request->string('reason')->toString())));
    }
}
