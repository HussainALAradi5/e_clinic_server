<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Concerns\ApiResponse;
use App\Interfaces\Accounting\MonthSummaryServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner's month-end summary (`?year=&month=`, default last month), compared with the month before.
 */
final class MonthSummaryController
{
    use ApiResponse;

    public function __construct(private readonly MonthSummaryServiceInterface $summaries) {}

    public function show(Request $request): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $default = CarbonImmutable::now()->subMonthNoOverflow();

        return $this->ok($this->summaries->summary($request->integer('year', $default->year), $request->integer('month', $default->month)));
    }
}
