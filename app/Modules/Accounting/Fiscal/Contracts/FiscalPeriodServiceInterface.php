<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Contracts;

use App\Modules\Accounting\Fiscal\Data\FiscalYearData;
use App\Modules\Accounting\Fiscal\Models\FiscalPeriod;
use App\Modules\Accounting\Fiscal\Models\FiscalYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface FiscalPeriodServiceInterface
{
    /**
     * Years (newest first) with their periods.
     *
     * @return Collection<int, FiscalYear>
     */
    public function listYears(): Collection;

    public function findYear(string $id): FiscalYear;

    public function findPeriod(string $id): FiscalPeriod;

    /** Creates a year with 12 monthly periods (+ optional adjustment period 13). */
    public function createYear(FiscalYearData $data): FiscalYear;

    /** Open period containing the date — used by every posting; missing / closed / locked → ClosedFiscalPeriodException. */
    public function periodFor(CarbonInterface $date): FiscalPeriod;

    /** Period containing the date whatever its status (drafts, closing entries); missing → ClosedFiscalPeriodException. */
    public function periodContaining(CarbonInterface $date): FiscalPeriod;

    /** Month-end close, in order; no drafts or open cash sessions dated in the period. */
    public function closePeriod(FiscalPeriod $period): FiscalPeriod;

    /** Reopens a closed period (latest first); never a locked one or one of a closed year. */
    public function reopenPeriod(FiscalPeriod $period, string $reason): FiscalPeriod;

    /** Year-end close: every period closed, then the closing entry moves revenue / expense into retained earnings. */
    public function closeYear(FiscalYear $year): FiscalYear;

    /** Permanently locks a closed year and its periods (after the external audit). */
    public function lockYear(FiscalYear $year): FiscalYear;
}
