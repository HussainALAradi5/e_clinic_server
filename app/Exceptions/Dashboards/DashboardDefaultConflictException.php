<?php

declare(strict_types=1);

namespace App\Exceptions\Dashboards;

use App\Exceptions\Shared\ConflictException;

/** Two people made different dashboards the default of one section at the same moment; the database kept one. */
final class DashboardDefaultConflictException extends ConflictException
{
    protected string $errorCode = 'DASHBOARD_DEFAULT_CONFLICT';
}
