<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Accounting\FiscalYearRequest;
use App\Http\Resources\Accounting\FiscalPeriodResource;
use App\Http\Resources\Accounting\FiscalYearResource;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
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
