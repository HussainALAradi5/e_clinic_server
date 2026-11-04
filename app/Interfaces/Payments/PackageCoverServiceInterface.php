<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Models\Appointments\Appointment;
use App\Models\Payments\Invoice;
use App\Models\Payments\PackageSession;

/**
 * How bought packages are used. Rules: a package counts only once its invoice is fully paid; a visit of the
 * covered service, on a day within the package's validity, uses one session instead of being invoiced (its share
 * of the price moves from 2510 Unearned package revenue to the service's revenue); unused sessions are forfeited
 * when the package expires.
 */
interface PackageCoverServiceInterface
{
    /** Uses a session of an active package for this visit, once; null when no package covers it. */
    public function coverVisit(Appointment $appointment): ?PackageSession;

    /** Activates the packages sold on this invoice once it is fully paid. */
    public function activatePaid(Invoice $invoice): int;

    /** Cancels packages still awaiting payment on a cancelled invoice. */
    public function cancelUnpaid(Invoice $invoice): int;

    /** Expires packages past their validity, forfeiting the unused sessions' value. */
    public function expireDue(): int;
}
