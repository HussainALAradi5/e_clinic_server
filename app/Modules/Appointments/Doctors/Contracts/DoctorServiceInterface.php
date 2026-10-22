<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Contracts;

use App\Modules\Appointments\Doctors\Data\DoctorData;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DoctorServiceInterface
{
    /**
     * Doctors by specialty, name, accepting flag.
     *
     * @return LengthAwarePaginator<int, Doctor>|CursorPaginator<int, Doctor>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Doctor with person, employee, specialty, billed services and default room (one id across all). */
    public function find(string $id): Doctor;

    /** Adds the doctor profile to an active employee; the account becomes type doctor with the doctor role. */
    public function create(DoctorData $data): Doctor;

    /** Profile and billed services (price changes are made in the price list). */
    public function update(Doctor $doctor, DoctorData $data): Doctor;

    /** Turns new bookings on / off (existing bookings untouched). */
    public function setAcceptingPatients(Doctor $doctor, bool $accepting): Doctor;
}
