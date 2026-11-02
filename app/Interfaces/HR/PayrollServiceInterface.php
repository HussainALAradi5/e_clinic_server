<?php

declare(strict_types=1);

namespace App\Interfaces\HR;

use App\Dtos\HR\PayrollPaymentData;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayrollServiceInterface
{
    /**
     * Runs by year / status (payroll staff only).
     *
     * @return LengthAwarePaginator<int, PayrollRun>|CursorPaginator<int, PayrollRun>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Run with its payslips and totals. */
    public function find(string $id): PayrollRun;

    /** Opens a draft run for a month that has no other run (cancelled ones aside). */
    public function createRun(int $year, int $month): PayrollRun;

    /** (Re)calculates every payslip of a draft / calculated run once the month has ended. */
    public function calculate(PayrollRun $run): PayrollRun;

    /** Calculated → approved by someone other than who calculated it: posts the salary accrual and locks attendance. */
    public function approve(PayrollRun $run): PayrollRun;

    /** Approved → paid after the bank transfer: posts the settlement. */
    public function markPaid(PayrollRun $run, PayrollPaymentData $data): PayrollRun;

    /** Cancels a run before payment (an approved run's accrual is reversed and attendance unlocked). */
    public function cancel(PayrollRun $run, string $reason): PayrollRun;

    /**
     * Payslips — all for payroll staff, otherwise one's own of approved or paid runs.
     *
     * @return LengthAwarePaginator<int, Payslip>|CursorPaginator<int, Payslip>
     */
    public function payslips(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Payslip with its lines. */
    public function payslip(string $id): Payslip;
}
