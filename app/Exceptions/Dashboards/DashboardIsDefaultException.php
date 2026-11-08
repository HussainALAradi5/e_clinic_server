<?php

declare(strict_types=1);

namespace App\Exceptions\Dashboards;

use App\Exceptions\Shared\ConflictException;

/** A section's default dashboard cannot be deleted: its page would go empty. Make another one the default first. */
final class DashboardIsDefaultException extends ConflictException
{
    protected string $errorCode = 'DASHBOARD_IS_DEFAULT';
}
