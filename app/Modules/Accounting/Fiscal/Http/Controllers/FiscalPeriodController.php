<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Http\Controllers;

use App\Modules\Accounting\Fiscal\Contracts\FiscalPeriodServiceInterface;
use App\Modules\Accounting\Fiscal\Http\Requests\FiscalYearRequest;
use App\Modules\Accounting\Fiscal\Http\Resources\FiscalPeriodResource;
use App\Modules\Accounting\Fiscal\Http\Resources\FiscalYearResource;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FiscalPeriodController
{
    use ApiResponse;

    public function __construct(private readonly FiscalPeriodServiceInterface $fiscal) {}

    public function index(): JsonResponse
    {
        return $this->ok(FiscalYearResource::collection($this->fiscal->listYears()));
    }

    public function store(FiscalYearRequest $request): JsonResponse
    {
        return $this->created(new FiscalYearResource($this->fiscal->createYear($request->toData())));
    }

    public function closePeriod(string $period): JsonResponse
    {
        return $this->ok(new FiscalPeriodResource($this->fiscal->closePeriod($this->fiscal->findPeriod($period))));
    }

    public function reopenPeriod(Request $request, string $period): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new FiscalPeriodResource($this->fiscal->reopenPeriod($this->fiscal->findPeriod($period), $request->string('reason')->toString())));
    }

    public function closeYear(string $fiscalYear): JsonResponse
    {
        return $this->ok(new FiscalYearResource($this->fiscal->closeYear($this->fiscal->findYear($fiscalYear))));
    }

    public function lockYear(string $fiscalYear): JsonResponse
    {
        return $this->ok(new FiscalYearResource($this->fiscal->lockYear($this->fiscal->findYear($fiscalYear))));
    }
}
