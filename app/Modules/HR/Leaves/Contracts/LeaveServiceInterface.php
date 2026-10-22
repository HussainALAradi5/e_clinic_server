<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Contracts;

use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Leaves\Data\LeaveRequestData;
use App\Modules\HR\Leaves\Data\LeaveTypeData;
use App\Modules\HR\Leaves\Models\LeaveBalance;
use App\Modules\HR\Leaves\Models\LeaveRequest;
use App\Modules\HR\Leaves\Models\LeaveType;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LeaveServiceInterface
{
    /**
     * Leave types (active ones unless archived are asked for too).
     *
     * @return Collection<int, LeaveType>
     */
    public function listTypes(bool $withArchived = false): Collection;

    /** Creates or updates a leave type. */
    public function saveType(LeaveTypeData $data, ?LeaveType $type = null): LeaveType;

    /**
     * The employee's balances of a year (created on first use), with remaining days.
     *
     * @return Collection<int, LeaveBalance>
     */
    public function balances(Employee $employee, int $year): Collection;

    /** **Internal / yearly** — creates missing balances: quota pro-rated by hire month, carry-forward from last year. */
    public function initializeBalances(Employee $employee, int $year): void;

    /**
     * Requests by status, employee, type, dates; staff see their own.
     *
     * @return LengthAwarePaginator<int, LeaveRequest>|CursorPaginator<int, LeaveRequest>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): LeaveRequest;

    /** Requests leave: working days only, notice, overlap and balance checks; waits as a draft when a document is needed. */
    public function request(Employee $employee, LeaveRequestData $data): LeaveRequest;

    /** Sends a draft (its document attached) for approval. */
    public function submit(LeaveRequest $request): LeaveRequest;

    /** Approves a pending request (not one's own): days used, attendance and the provider's schedule updated. */
    public function approve(LeaveRequest $request, ?string $notes = null, bool $cancelAffected = false): LeaveRequest;

    /** Rejects a pending request (not one's own); its days are released. */
    public function reject(LeaveRequest $request, string $notes): LeaveRequest;

    /** The requester cancels a draft or pending request; an approved one that has not started is cancelled by HR. */
    public function cancel(LeaveRequest $request): LeaveRequest;
}
