<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\Appointments\AppointmentCompleted;
use App\Interfaces\Appointments\ClinicalOrderServiceInterface;

/** The referred visit was completed → its referral is completed (closes the loop). */
final class CloseReferralOnVisit
{
    public function __construct(private readonly ClinicalOrderServiceInterface $orders) {}

    public function handle(AppointmentCompleted $event): void
    {
        $this->orders->referralVisitCompleted($event->appointment);
    }
}
