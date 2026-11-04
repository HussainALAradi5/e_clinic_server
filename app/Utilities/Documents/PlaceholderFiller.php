<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

use App\Enums\Documents\TemplatePlaceholder;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\RecordDiagnosis;
use App\Models\HR\Employee;
use App\Utilities\Shared\ClinicClock;

/**
 * Fills a template's `{{placeholders}}` from a visit: the patient, the visit, its diagnoses, the author and the
 * clinic. Values are HTML-escaped (the body is rich text); unknown placeholders are left as written.
 */
final class PlaceholderFiller
{
    public function __construct(
        private readonly DocumentFormat $format,
        private readonly ClinicClock $clock,
    ) {}

    public function fill(string $body, Appointment $visit, Employee $author, string $clinicName): string
    {
        $values = $this->values($visit, $author, $clinicName);

        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $m) use ($values): string {
            $placeholder = TemplatePlaceholder::tryFrom($m[1]);

            return $placeholder !== null ? e($values[$placeholder->value]) : $m[0];
        }, $body);
    }

    /**
     * @return array<string, string>
     */
    private function values(Appointment $visit, Employee $author, string $clinicName): array
    {
        $person = $visit->patient->person;
        $diagnoses = $visit->medicalRecord?->diagnoses
            ->sortByDesc('is_primary')
            ->map(fn (RecordDiagnosis $d): string => $d->code->title.' ('.$d->code->code.')')
            ->implode('; ');

        return [
            TemplatePlaceholder::PatientName->value => $person->full_name,
            TemplatePlaceholder::PatientCpr->value => $person->national_id ?? '—',
            TemplatePlaceholder::PatientFileNo->value => $person->code,
            TemplatePlaceholder::PatientAge->value => (string) $person->date_of_birth->age,
            TemplatePlaceholder::PatientSex->value => mb_strtolower($person->gender->label()),
            TemplatePlaceholder::PatientBirthDate->value => $this->format->date($person->date_of_birth),
            TemplatePlaceholder::VisitDate->value => $this->format->date($visit->scheduled_date),
            TemplatePlaceholder::VisitTime->value => $this->format->clockTime($visit->start_time).' – '.$this->format->clockTime($visit->end_time),
            TemplatePlaceholder::VisitReason->value => $visit->reason ?? '—',
            TemplatePlaceholder::Diagnosis->value => $diagnoses !== null && $diagnoses !== '' ? $diagnoses : '—',
            TemplatePlaceholder::AuthorName->value => $author->person->full_name,
            TemplatePlaceholder::AuthorTitle->value => $author->job_title,
            TemplatePlaceholder::ClinicName->value => $clinicName,
            TemplatePlaceholder::Today->value => $this->format->date($this->clock->today()),
        ];
    }
}
