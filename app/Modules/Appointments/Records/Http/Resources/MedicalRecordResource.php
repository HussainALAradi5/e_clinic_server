<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Http\Resources;

use App\Modules\Appointments\Records\Models\MedicalRecord;
use App\Modules\Appointments\Records\Models\RecordAddendum;
use App\Modules\Appointments\Records\Models\RecordDiagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visit record. Without patients.view_history (doctors) only vitals and the signature state are shown (no notes, no
 * diagnoses) — nurses record and read vitals only.
 *
 * @mixin MedicalRecord
 */
final class MedicalRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Visit notes, diagnoses and addenda are the patient's health history: doctors only.
        $medical = $request->user()?->can('patients.view_history') ?? false;

        return [
            'appointment_id' => $this->id,
            'exists' => $this->exists,
            ...collect(MedicalRecord::NOTES)->mapWithKeys(fn (string $f): array => [$f => $this->when($medical, fn (): ?string => $this->getAttribute($f))])->all(),
            'vitals' => [
                'temperature_c' => $this->temperature_c === null ? null : (float) $this->temperature_c,
                'pulse_bpm' => $this->pulse_bpm,
                'systolic_bp' => $this->systolic_bp,
                'diastolic_bp' => $this->diastolic_bp,
                'respiratory_rate' => $this->respiratory_rate,
                'spo2_percent' => $this->spo2_percent,
                'weight_kg' => $this->weight_kg === null ? null : (float) $this->weight_kg,
                'height_cm' => $this->height_cm === null ? null : (float) $this->height_cm,
                'bmi' => $this->bmi(),
            ],
            'diagnoses' => $this->when($medical, fn (): array => $this->diagnoses->map(fn (RecordDiagnosis $d): array => [
                'icd10_code_id' => $d->icd10_code_id,
                'code' => $d->code->code,
                'title' => $d->code->title,
                'title_ar' => $d->code->title_ar,
                'is_primary' => $d->is_primary,
                'notes' => $d->notes,
            ])->all()),
            'addenda' => $this->when($medical, fn (): array => $this->addenda->map(fn (RecordAddendum $a): array => [
                'id' => $a->id,
                'text' => $a->text,
                'author' => $a->author?->person?->full_name,
                'created_at' => $a->created_at->toIso8601String(),
            ])->all()),
            'is_signed' => $this->isSigned(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_by' => $this->signer === null ? null : ['id' => $this->signer->id, 'full_name' => $this->signer->person->full_name],
            'version' => $this->exists ? $this->version : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
