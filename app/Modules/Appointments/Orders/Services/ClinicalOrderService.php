<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Services;

use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Reference\CompanyType;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Orders\Contracts\ClinicalOrderServiceInterface;
use App\Modules\Appointments\Orders\Data\ClinicalOrderData;
use App\Modules\Appointments\Orders\Data\OrderResultData;
use App\Modules\Appointments\Orders\Events\ClinicalOrderCompleted;
use App\Modules\Appointments\Orders\Events\ClinicalOrderCreated;
use App\Modules\Appointments\Orders\Exceptions\OrderNotEditableException;
use App\Modules\Appointments\Orders\Exceptions\OrderResultMissingException;
use App\Modules\Appointments\Orders\Exceptions\ReferralTargetMissingException;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Records\Contracts\ConsentServiceInterface;
use App\Modules\Appointments\Rooms\Exceptions\RoomUnavailableException;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imaging, lab, ECG orders and referrals: one model, one priority work list, results as attachments.
 *
 * @extends BaseEntityService<ClinicalOrder>
 */
final class ClinicalOrderService extends BaseEntityService implements ClinicalOrderServiceInterface
{
    public const RELATIONS = [
        'patient.person', 'orderedBy.person', 'billableService', 'appointment', 'treatment',
        'referredToDoctor.person', 'referredToSpecialty', 'referredToCompany', 'referralAppointment',
        'room', 'performer.person', 'reporter.person',
    ];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly ConsentServiceInterface $consents,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function worklist(array $types): Collection
    {
        return $this->query()
            ->whereIn('status', [GenericStatus::Pending->value, GenericStatus::InProgress->value])
            ->when($types !== [], fn (Builder $q) => $q->whereIn('type', array_map(fn (ClinicalOrderType $t): string => $t->value, $types)))
            ->get()
            ->sortBy([fn (ClinicalOrder $a, ClinicalOrder $b): int => $b->priority->rank() <=> $a->priority->rank(), ['created_at', 'asc']])
            ->values()
            ->toBase();
    }

    public function find(string $id): ClinicalOrder
    {
        return $this->query()->with(['attachments', 'invoiceLine.invoice'])->findOrFail($id);
    }

    public function findWithDeleted(string $id): ClinicalOrder
    {
        return parent::findWithDeleted($id);
    }

    public function create(ClinicalOrderData $data): ClinicalOrder
    {
        $values = [...$data->values, 'ordered_by' => $data->values['ordered_by'] ?? $this->actingDoctorId()];
        $this->assertCoherent($values);

        $order = ClinicalOrder::query()->create([...$values, 'status' => GenericStatus::Pending]);
        ClinicalOrderCreated::dispatch($order);

        return $this->find($order->id);
    }

    public function update(ClinicalOrder $order, ClinicalOrderData $data, int $version): ClinicalOrder
    {
        return DB::transaction(function () use ($order, $data, $version): ClinicalOrder {
            $order->assertVersion($version);
            if ($order->status !== GenericStatus::Pending) {
                throw new OrderNotEditableException;
            }
            $order->fill(collect($data->values)->except(['patient_id', 'ordered_by', 'appointment_id', 'type'])->all());
            $this->assertCoherent($order->getAttributes());
            $order->save();

            return $this->find($order->id);
        });
    }

    public function start(ClinicalOrder $order, ?string $roomId): ClinicalOrder
    {
        $this->assertTransition($order, GenericStatus::InProgress);
        if ($order->isReferral() && ! $order->isExternalReferral()) {
            // Internal referrals start when the referred visit is booked (linkReferralAppointment).
            throw new InvalidStateTransitionException(context: ['from' => $order->status->value, 'to' => GenericStatus::InProgress->value, 'reason' => 'book_the_referred_visit']);
        }

        $roomType = $order->type->roomType();
        if ($roomType !== null) {
            $room = $roomId === null ? null : Room::query()->find($roomId);
            if (! $room instanceof Room || ! $room->isBookable() || $room->type !== $roomType) {
                throw new RoomUnavailableException;
            }
        }
        $this->consents->assertConsent($order->patient, $order->billableService?->consent_type, $order);

        $order->forceFill([
            'status' => GenericStatus::InProgress,
            'room_id' => $roomType !== null ? $roomId : null,
            'performed_by' => $this->actingEmployeeId(),
            'started_at' => $this->clock->now(),
        ])->save();

        return $this->find($order->id);
    }

    public function complete(ClinicalOrder $order, OrderResultData $data): ClinicalOrder
    {
        $this->assertTransition($order, GenericStatus::Completed);
        if (trim($data->report) === '' || ($order->type->isImaging() && ! $order->attachments()->exists())) {
            throw new OrderResultMissingException;
        }

        $order->forceFill([
            'status' => GenericStatus::Completed,
            'report' => $data->report,
            'is_abnormal' => $data->isAbnormal,
            'reported_by' => $data->reportedBy ?? (Doctor::query()->whereKey(Auth::id())->exists() ? Auth::id() : null),
            'performed_by' => $order->performed_by ?? $this->actingEmployeeId(),
            'completed_at' => $this->clock->now(),
        ])->save();
        ClinicalOrderCompleted::dispatch($order);

        return $this->find($order->id);
    }

    public function linkReferralAppointment(ClinicalOrder $order, Appointment $appointment): ClinicalOrder
    {
        if (! $order->isReferral() || $order->isExternalReferral() || $order->status !== GenericStatus::Pending) {
            throw new InvalidStateTransitionException(context: ['from' => $order->status->value, 'to' => GenericStatus::InProgress->value]);
        }
        $doctor = Doctor::query()->find($appointment->provider_id);
        $matches = $appointment->patient_id === $order->patient_id && $doctor instanceof Doctor
            && ($order->referred_to_doctor_id === null || $order->referred_to_doctor_id === $doctor->id)
            && ($order->referred_to_specialty_id === null || $order->referred_to_specialty_id === $doctor->specialty_id);
        if (! $matches || ! $appointment->isActive()) {
            throw ValidationException::withMessages(['appointment_id' => [__('errors.REFERRAL_APPOINTMENT_MISMATCH')]]);
        }

        $order->forceFill(['referral_appointment_id' => $appointment->id, 'status' => GenericStatus::InProgress, 'started_at' => $this->clock->now()])->save();

        return $this->find($order->id);
    }

    public function referralVisitCompleted(Appointment $appointment): void
    {
        ClinicalOrder::query()
            ->where('referral_appointment_id', $appointment->id)
            ->where('status', GenericStatus::InProgress->value)
            ->get()
            ->each(function (ClinicalOrder $order): void {
                $order->forceFill(['status' => GenericStatus::Completed, 'completed_at' => $this->clock->now()])->save();
                ClinicalOrderCompleted::dispatch($order);
            });
    }

    public function cancel(ClinicalOrder $order, string $reason): ClinicalOrder
    {
        $this->assertTransition($order, GenericStatus::Cancelled);
        $order->forceFill(['status' => GenericStatus::Cancelled, 'cancellation_reason' => $reason])->save();

        return $this->find($order->id);
    }

    public function delete(Model $model): void
    {
        parent::delete($model);
    }

    /** Only finished orders; also used by bulk archive. */
    public function archive(Model $model): ClinicalOrder
    {
        if (! $model->status->isFinal()) {
            throw new InvalidStateTransitionException(context: ['from' => $model->status->value, 'to' => 'archived']);
        }

        return $model->isArchived() ? $model : $this->find(parent::archive($model)->id);
    }

    public function unarchive(Model $model): ClinicalOrder
    {
        return $model->isArchived() ? $this->find(parent::unarchive($model)->id) : $model;
    }

    public function restore(Model $model): ClinicalOrder
    {
        return $model->isDeleted() ? $this->find(parent::restore($model)->id) : $model;
    }

    /**
     * @return Builder<ClinicalOrder>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(ClinicalOrder::query()->with(self::RELATIONS), 'orders', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var ClinicalOrder $model */
        if ($model->status !== GenericStatus::Pending) {
            throw new InvalidStateTransitionException(context: ['from' => $model->status->value, 'to' => 'deleted']);
        }
    }

    /**
     * Billed service of the right kind; referrals need a target and no service.
     *
     * @param  array<string, mixed>  $values
     */
    private function assertCoherent(array $values): void
    {
        $type = $values['type'] instanceof ClinicalOrderType ? $values['type'] : ClinicalOrderType::from((string) $values['type']);

        if ($type === ClinicalOrderType::Referral) {
            if (($values['referred_to_doctor_id'] ?? null) === null && ($values['referred_to_specialty_id'] ?? null) === null && ($values['referred_to_company_id'] ?? null) === null) {
                throw new ReferralTargetMissingException;
            }
            $company = ($values['referred_to_company_id'] ?? null) === null ? null : Company::query()->find((string) $values['referred_to_company_id']);
            if (($values['referred_to_company_id'] ?? null) !== null && $company?->type !== CompanyType::Hospital) {
                throw ValidationException::withMessages(['referred_to_company_id' => [__('errors.REFERRAL_COMPANY_NOT_HOSPITAL')]]);
            }

            return;
        }

        $service = BillableService::query()->find($values['billable_service_id'] ?? null);
        if (! $service instanceof BillableService || $service->category !== $type->serviceCategory()) {
            throw ValidationException::withMessages(['billable_service_id' => [__('errors.ORDER_SERVICE_MISMATCH')]]);
        }
    }

    private function assertTransition(ClinicalOrder $order, GenericStatus $to): void
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new InvalidStateTransitionException(context: ['from' => $order->status->value, 'to' => $to->value]);
        }
    }

    /** Staff member performing the order (null for accounts without an employee file, e.g. a super admin). */
    private function actingEmployeeId(): ?string
    {
        $id = Auth::id();

        return is_string($id) && Employee::query()->whereKey($id)->exists() ? $id : null;
    }

    /** The signed-in doctor orders for themself; anyone else names the doctor. */
    private function actingDoctorId(): string
    {
        $id = Auth::id();
        if (! is_string($id) || ! Doctor::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['ordered_by' => [__('validation.required', ['attribute' => 'ordered by'])]]);
        }

        return $id;
    }
}
