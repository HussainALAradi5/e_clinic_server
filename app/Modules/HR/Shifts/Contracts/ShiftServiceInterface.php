<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Contracts;

use App\Modules\HR\Shifts\Data\ShiftData;
use App\Modules\HR\Shifts\Models\Shift;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Work shifts (HR and admins): fixed or flexible, each with a code, and the employees on each. Moving someone to a
 * shift sets their working hours from it, which attendance measures late / on time / overtime against.
 */
interface ShiftServiceInterface
{
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** A shift with its employees (name, photo, job title, department). */
    public function findShift(string $id): Shift;

    public function createShift(ShiftData $data): Shift;

    /** Changing the hours updates every employee on the shift. */
    public function updateShift(Shift $shift, ShiftData $data): Shift;

    /** Only an empty shift can be deleted (`SHIFT_HAS_EMPLOYEES`). */
    public function deleteShift(Shift $shift): void;

    /**
     * Puts employees on this shift (moving them from any other), and sets their working hours from it.
     *
     * @param  list<string>  $employeeIds
     */
    public function assign(Shift $shift, array $employeeIds): Shift;

    /** Takes someone off the shift (their last working hours stay). */
    public function unassign(Shift $shift, string $employeeId): Shift;
}
