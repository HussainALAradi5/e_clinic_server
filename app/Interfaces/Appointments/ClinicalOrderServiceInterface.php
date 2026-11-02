<?php

declare(strict_types=1);

namespace App\Interfaces\Appointments;

use App\Dtos\Appointments\ClinicalOrderData;
use App\Dtos\Appointments\OrderResultData;
use App\Enums\Appointments\ClinicalOrderType;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClinicalOrderServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, ClinicalOrder>|CursorPaginator<int, ClinicalOrder>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /**
     * Open orders of some types, urgent → high → medium then oldest (radiology, lab or referral desk).
     *
     * @param  list<ClinicalOrderType>  $types
     * @return Collection<int, ClinicalOrder>
     */
    public function worklist(array $types): Collection;

    public function find(string $id): ClinicalOrder;

    public function findWithDeleted(string $id): ClinicalOrder;

    /** A doctor orders imaging, a lab test, an ECG or writes a referral. */
    public function create(ClinicalOrderData $data): ClinicalOrder;

    /** Edits a `pending` order. */
    public function update(ClinicalOrder $order, ClinicalOrderData $data, int $version): ClinicalOrder;

    /** `pending → in_progress` in a room of the right type (external referral: letter sent, no room). */
    public function start(ClinicalOrder $order, ?string $roomId): ClinicalOrder;

    /** `in_progress → completed` with the report (imaging needs ≥ 1 image attached to the order). */
    public function complete(ClinicalOrder $order, OrderResultData $data): ClinicalOrder;

    /** Internal referral → `in_progress`, linked to the visit booked with the target doctor / specialty. */
    public function linkReferralAppointment(ClinicalOrder $order, Appointment $appointment): ClinicalOrder;

    /** **Listener** — the referred visit was completed: the referral is completed. */
    public function referralVisitCompleted(Appointment $appointment): void;

    public function cancel(ClinicalOrder $order, string $reason): ClinicalOrder;

    /** Soft-deletes a `pending` order created by mistake. */
    public function delete(ClinicalOrder $order): void;

    public function archive(ClinicalOrder $order): ClinicalOrder;

    public function unarchive(ClinicalOrder $order): ClinicalOrder;

    public function restore(ClinicalOrder $order): ClinicalOrder;
}
