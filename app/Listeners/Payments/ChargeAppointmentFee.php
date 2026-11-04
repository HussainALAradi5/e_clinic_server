<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Appointments\AppointmentCancelled;
use App\Events\Appointments\AppointmentNoShow;
use App\Interfaces\Payments\AppointmentFeeServiceInterface;

/**
 * A visit was missed or cancelled: charges the fee its rules call for (none for most cancellations). A failure is
 * reported but never undoes the cancellation or the no-show.
 */
final readonly class ChargeAppointmentFee
{
    public function __construct(private AppointmentFeeServiceInterface $fees) {}

    public function handle(AppointmentNoShow|AppointmentCancelled $event): void
    {
        rescue(fn () => $this->fees->chargeFor($event->appointment), report: true);
    }
}
