<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Contracts;

use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Records\Data\MedicalRecordData;
use App\Modules\Appointments\Records\Models\Icd10Code;
use App\Modules\Appointments\Records\Models\MedicalRecord;
use App\Modules\Appointments\Records\Models\RecordAddendum;
use Illuminate\Support\Collection;

interface MedicalRecordServiceInterface
{
    /** The visit's record with diagnoses and addenda (an unsaved empty draft before the first save). */
    public function get(Appointment $appointment): MedicalRecord;

    /** Saves notes, vitals and diagnoses while unsigned (vitals may be entered by a nurse at check-in). */
    public function save(Appointment $appointment, MedicalRecordData $data): MedicalRecord;

    /** The visit's provider signs; the record becomes read-only. */
    public function sign(Appointment $appointment): MedicalRecord;

    /** Dated correction of a signed record. */
    public function addAddendum(Appointment $appointment, string $text): RecordAddendum;

    /**
     * ICD-10 codes and titles (English / Arabic), code matches first.
     *
     * @return Collection<int, Icd10Code>
     */
    public function searchIcd10(string $term, int $limit = 20): Collection;
}
