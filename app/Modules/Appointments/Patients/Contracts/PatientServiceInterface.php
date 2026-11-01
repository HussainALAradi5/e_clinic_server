<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Contracts;

use App\Modules\Appointments\Patients\Data\PatientData;
use App\Modules\Appointments\Patients\Data\PatientTimelineEntry;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Patients\Models\PatientAccessGrant;
use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PatientServiceInterface
{
    /**
     * Search by name, MRN or phone (joined with persons), or exact national ID.
     *
     * @return LengthAwarePaginator<int, Patient>|CursorPaginator<int, Patient>
     */
    public function list(ListQuery $query, ?string $nationalId = null): LengthAwarePaginator|CursorPaginator;

    /** Patient with person, address, insurer, portal account and appointment counts. */
    public function find(string $id): Patient;

    /** Registers a patient for an existing or new person (duplicates reported, never re-created). */
    public function create(PatientData $data): Patient;

    /** Medical / insurance data (personal data → PUT /people/{id}). */
    public function update(Patient $patient, PatientData $data, int $version): Patient;

    /**
     * Portal: the patient's own contact details (person) and emergency contact (patient) in one step; null = unchanged.
     */
    public function updateContact(Patient $patient, ?PersonData $person, int $personVersion, ?PatientData $data, int $version): Patient;

    /** Portal account with the same id (user_type patient); the patient gets the invitation email. */
    public function createPortalAccount(Patient $patient): User;

    /**
     * Appointments, records, orders, prescriptions, invoices — newest first (sections the caller can read).
     *
     * @return Collection<int, PatientTimelineEntry>
     */
    public function timeline(Patient $patient): Collection;

    /**
     * Finds a patient by file number or exact national ID whether or not they are in the caller's care — identity
     * only (no medical data), with `in_care`, so a clinician can confirm the person before breaking the glass.
     */
    public function lookup(?string $code, ?string $nationalId): ?Patient;

    /**
     * Break the glass: opens the patient's file (and history) to the clinician for a few hours, with a reason that is
     * kept and audited; admins are told.
     */
    public function grantEmergencyAccess(Patient $patient, User $clinician, string $reason): PatientAccessGrant;
}
