<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Contracts;

use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionData;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Electronic prescriptions. Status is driven by the dispensed quantities (pending → in_progress → completed).
 */
interface PrescriptionServiceInterface
{
    /**
     * By patient, doctor, status, date.
     *
     * @return LengthAwarePaginator<int, Prescription>|CursorPaginator<int, Prescription>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /**
     * Pharmacy queue: pending / partly dispensed and still valid, oldest first.
     *
     * @return LengthAwarePaginator<int, Prescription>|CursorPaginator<int, Prescription>
     */
    public function queue(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Prescription with items (prescribed / dispensed / remaining) and its dispensations. */
    public function find(string $id): Prescription;

    /** The doctor writes it; duplicate-therapy and allergy warnings must be confirmed. */
    public function create(PrescriptionData $data): Prescription;

    /** Edits a prescription nothing was dispensed from yet. */
    public function update(Prescription $prescription, PrescriptionData $data): Prescription;

    /** Stops what is left to dispense. */
    public function cancel(Prescription $prescription, string $reason): Prescription;
}
