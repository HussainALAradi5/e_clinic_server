<?php

declare(strict_types=1);

namespace App\Interfaces\Accounting;

/**
 * The owner's month in one view, next to the month before: revenue, expenses and the result (ledger, posted);
 * money collected by method and refunded; what patients and insurers still owe; balances held for patients and
 * unearned packages; visits, no-shows, cancellations and new patients; fees and packages; the top five doctors.
 */
interface MonthSummaryServiceInterface
{
    /** @return array<string, mixed> */
    public function summary(int $year, int $month): array;
}
