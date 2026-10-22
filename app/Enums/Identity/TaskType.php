<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of long-running background task.
 */
enum TaskType: string implements HasLabel
{
    use EnumHelpers;

    case ReportExport = 'report_export';
    case AuditExport = 'audit_export';
    case TransactionExport = 'transaction_export';
    case PayrollCalculation = 'payroll_calculation';
    case PayslipEmailing = 'payslip_emailing';
    case FiscalYearClose = 'fiscal_year_close';
    case BulkNotification = 'bulk_notification';
    case BulkAction = 'bulk_action';
    case AttachmentsZip = 'attachments_zip';
    case ListExport = 'list_export';
}
