<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Dtos\HR\PayrollPaymentData;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\HR\PayrollRunResource;
use App\Http\Resources\HR\PayslipResource;
use App\Interfaces\HR\PayrollServiceInterface;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PayrollController
{
    use ApiResponse;

    public function __construct(private readonly PayrollServiceInterface $payroll) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, PayrollRun::listQueryConfig());

        return $this->paginated(PayrollRunResource::collection($this->payroll->list($query)), $query);
    }

    public function show(string $payrollRun): JsonResponse
    {
        return $this->ok(new PayrollRunResource($this->payroll->find($payrollRun)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12']]);

        return $this->created(new PayrollRunResource($this->payroll->createRun($request->integer('year'), $request->integer('month'))));
    }

    public function calculate(string $payrollRun): JsonResponse
    {
        return $this->ok(new PayrollRunResource($this->payroll->calculate($this->payroll->find($payrollRun))));
    }

    public function approve(string $payrollRun): JsonResponse
    {
        return $this->ok(new PayrollRunResource($this->payroll->approve($this->payroll->find($payrollRun))));
    }

    public function markPaid(Request $request, string $payrollRun, ClinicClock $clock): JsonResponse
    {
        $request->validate(['paid_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'reference' => ['nullable', 'string', 'max:100']]);
        $data = new PayrollPaymentData(
            $request->filled('paid_on') ? CarbonImmutable::parse($request->string('paid_on')->toString()) : $clock->today(),
            $request->filled('reference') ? $request->string('reference')->toString() : null,
        );

        return $this->ok(new PayrollRunResource($this->payroll->markPaid($this->payroll->find($payrollRun), $data)));
    }

    public function cancel(Request $request, string $payrollRun): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new PayrollRunResource($this->payroll->cancel($this->payroll->find($payrollRun), $request->string('reason')->toString())));
    }

    public function payslips(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Payslip::listQueryConfig());

        return $this->paginated(PayslipResource::collection($this->payroll->payslips($query)), $query);
    }

    public function payslip(string $payslip): JsonResponse
    {
        return $this->ok(new PayslipResource($this->payroll->payslip($payslip)));
    }
}
