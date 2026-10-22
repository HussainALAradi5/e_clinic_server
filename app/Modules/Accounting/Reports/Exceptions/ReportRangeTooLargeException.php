<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Reports\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The date range exceeds the maximum of the report (24 months).
 */
final class ReportRangeTooLargeException extends BusinessValidationException
{
    protected string $errorCode = 'REPORT_RANGE_TOO_LARGE';
}
