<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dtos\Accounting\FiscalYearData;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Accounting\FiscalYear;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Database\Seeder;

/**
 * The current calendar fiscal year (and next year from December), so the clinic can issue invoices and take
 * payments from the first day. Idempotent: an existing year is left alone.
 */
final class FiscalYearSeeder extends Seeder
{
    public function run(FiscalPeriodServiceInterface $fiscal, ClinicClock $clock): void
    {
        $today = $clock->today();
        $years = [$today->year, ...($today->month === 12 ? [$today->year + 1] : [])];

        foreach ($years as $year) {
            if (FiscalYear::query()->whereYear('start_date', $year)->exists()) {
                continue;
            }
            $fiscal->createYear(FiscalYearData::calendar($year));
        }
    }
}
