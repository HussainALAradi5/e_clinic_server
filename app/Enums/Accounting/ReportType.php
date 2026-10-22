<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Financial reports that can be exported.
 */
enum ReportType: string implements HasLabel
{
    use EnumHelpers;

    case TrialBalance = 'trial_balance';
    case GeneralLedger = 'general_ledger';
    case IncomeStatement = 'income_statement';
    case BalanceSheet = 'balance_sheet';
    case CashFlow = 'cash_flow';
    case RevenueByDoctor = 'revenue_by_doctor';
    case RevenueByService = 'revenue_by_service';
    case ReceivablesAging = 'receivables_aging';
}
