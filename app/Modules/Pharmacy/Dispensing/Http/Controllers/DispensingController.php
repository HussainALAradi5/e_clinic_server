<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Http\Controllers;

use App\Modules\Appointments\Appointments\Contracts\AppointmentServiceInterface;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Pharmacy\Dispensing\Contracts\DispensingServiceInterface;
use App\Modules\Pharmacy\Dispensing\Http\Requests\DispenseRequest;
use App\Modules\Pharmacy\Dispensing\Http\Resources\DispensationResource;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Prescriptions\Http\Resources\PrescriptionResource;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use App\Shared\Support\Recipients;
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
        $people = Recipients::withPermission($permission, [(string) $request->user()?->getAuthIdentifier()])
            ->filter(fn (Person $p): bool => Employee::query()->whereKey($p->id)->exists())
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
