<?php

declare(strict_types=1);

namespace App\Interfaces\Pharmacy;

use App\Dtos\Pharmacy\AdministerData;
use App\Dtos\Pharmacy\AppointmentMedicines;
use App\Dtos\Pharmacy\DispenseData;
use App\Models\Appointments\Appointment;
use App\Models\Pharmacy\Dispensation;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Medicine leaving stock for a patient. Both kinds share one flow: FEFO lots, stock movements, a line on the patient's
 * draft invoice (single billing path) and the cost-of-goods-sold entry. Requests are idempotent (Idempotency-Key).
 */
interface DispensingServiceInterface
{
    /**
     * By date, kind, dispenser, patient, prescription, appointment.
     *
     * @return LengthAwarePaginator<int, Dispensation>|CursorPaginator<int, Dispensation>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Dispensation with its lots, prescription and invoice line. */
    public function find(string $id): Dispensation;

    /** Pharmacy hand-over against a prescription (partial allowed) or over the counter. */
    public function dispense(DispenseData $data): Dispensation;

    /** Medicines given during a checked-in / in-progress session (`kind = administered`). */
    public function administer(Appointment $appointment, AdministerData $data): Dispensation;

    /** What was prescribed and what was given / handed over for one appointment. */
    public function forAppointment(Appointment $appointment): AppointmentMedicines;

    /** Same-day return to stock: reverses the movements, the draft invoice lines and the cost entry. */
    public function cancel(Dispensation $dispensation, string $reason): Dispensation;
}
