<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Contracts;

use App\Enums\Appointments\ConsentType;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Records\Data\ConsentData;
use App\Modules\Appointments\Records\Models\PatientConsent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ConsentServiceInterface
{
    /**
     * The patient's consents, newest first (validity is computed).
     *
     * @return Collection<int, PatientConsent>
     */
    public function list(Patient $patient): Collection;

    public function find(string $id): PatientConsent;

    /** Records a signed consent (the scanned form can be attached before or after). */
    public function record(Patient $patient, ConsentData $data): PatientConsent;

    /** The patient withdraws consent; work that needs it is blocked from now on. */
    public function revoke(PatientConsent $consent, string $reason): PatientConsent;

    /**
     * **Internal guard** before a visit, session or order that needs consent (service `consent_type` set):
     * a valid consent of that type for the patient, patient-wide or for this visit / its treatment / this order.
     */
    public function assertConsent(Patient $patient, ?ConsentType $type, ?Model $scope = null): void;
}
