<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Payments\AppointmentFeeStatus;
use App\Enums\Payments\AppointmentFeeType;
use App\Enums\Payments\InvoiceStatus;
use App\Exceptions\Payments\FeeAlreadyWaivedException;
use App\Exceptions\Payments\FeeNotWaivableException;
use App\Interfaces\Payments\AppointmentFeeServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Payments\AppointmentFee;
use App\Models\Payments\BillableService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class AppointmentFeeService implements AppointmentFeeServiceInterface
{
    private const RELATIONS = ['appointment', 'patient.person', 'invoice', 'waiver.person'];

    public function __construct(
        private readonly InvoiceServiceInterface $invoices,
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): AppointmentFee
    {
        return $this->query()->findOrFail($id);
    }

    public function feeTypeFor(Appointment $appointment): ?AppointmentFeeType
    {
        return match ($appointment->status) {
            AppointmentStatus::NoShow => AppointmentFeeType::NoShow,
            AppointmentStatus::Cancelled => $appointment->cancelled_by === CancelledBy::Patient
                && $appointment->cancelled_at !== null
                && $this->isLate($appointment, $appointment->cancelled_at) ? AppointmentFeeType::LateCancel : null,
            default => null,
        };
    }

    public function chargeFor(Appointment $appointment): ?AppointmentFee
    {
        $type = $this->feeTypeFor($appointment);
        $service = $type === null ? null : $this->service($type);
        if ($type === null || $service === null) {
            return null;
        }

        return DB::transaction(function () use ($appointment, $type, $service): AppointmentFee {
            // One fee per appointment, even when the event is handled twice.
            $existing = AppointmentFee::query()->where('appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $fee = AppointmentFee::query()->create([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'type' => $type,
                'status' => AppointmentFeeStatus::Charged,
                'billable_service_id' => $service->id,
                'amount' => $service->price,
                'currency' => $service->price->getCurrency()->getCurrencyCode(),
            ]);
            $invoice = $this->invoices->charge($fee);
            $fee->update(['invoice_id' => $invoice?->id]);

            return $fee;
        });
    }

    public function cancellationFee(Appointment $appointment): ?Money
    {
        if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled) || ! $this->isLate($appointment, $this->clock->now())) {
            return null;
        }

        return $this->service(AppointmentFeeType::LateCancel)?->price;
    }

    public function waive(AppointmentFee $fee, string $reason): AppointmentFee
    {
        return DB::transaction(function () use ($fee, $reason): AppointmentFee {
            $fee = AppointmentFee::query()->with('invoice')->lockForUpdate()->findOrFail($fee->id);
            if ($fee->isWaived()) {
                throw new FeeAlreadyWaivedException;
            }
            if ($fee->invoice !== null && $fee->invoice->status !== InvoiceStatus::Draft) {
                throw new FeeNotWaivableException(context: ['invoice' => $fee->invoice->invoice_number]);
            }
            $this->invoices->uncharge($fee);
            $fee->update([
                'status' => AppointmentFeeStatus::Waived,
                'waived_by' => Auth::id(),
                'waived_at' => $this->clock->now(),
                'waiver_reason' => $reason,
            ]);

            return $this->find($fee->id);
        });
    }

    /** Cancelling at [$at] is late: within the late-cancel window before the start (or after it). */
    private function isLate(Appointment $appointment, CarbonImmutable $at): bool
    {
        return $at->gt($appointment->startsAt()->subHours(Config::integer('payments.fees.late_cancel_hours')));
    }

    /** The fee's price-list item while it is in use and priced above zero. */
    private function service(AppointmentFeeType $type): ?BillableService
    {
        $service = BillableService::query()->where('code', $type->serviceCode())->where('is_archived', false)->first();

        return $service !== null && $service->price->isPositive() ? $service : null;
    }

    /** @return Builder<AppointmentFee> */
    private function query(): Builder
    {
        return OwnershipScope::apply(AppointmentFee::query()->with(self::RELATIONS), 'appointment_fees', Auth::user());
    }
}
