<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBuilder;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Models\Appointments\SickLeave;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Sick leave certificate: the patient (name, CPR, file no., sex), the days off as coloured tiles, the condition and
 * case description, the certificate wording, then the doctor's signature beside the clinic stamp. The condition is
 * printed only when the patient allowed it (`show_reason`).
 */
final class SickLeaveDocument implements DocumentBuilder
{
    public function __construct(
        private readonly DocumentFormat $format,
        private readonly ClinicProfileServiceInterface $clinic,
    ) {}

    public function with(): array
    {
        return ['patient.person', 'doctor.person', 'appointment'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var SickLeave $leave */
        $leave = $record;
        $f = $this->format;
        $person = $leave->patient->person;
        $cancelled = $leave->cancelled_at !== null;
        $disclosed = $leave->show_reason && $leave->reason !== null;

        return new DocumentPage(
            title: $f->text('sick_leave.title'),
            reference: $leave->code,
            fileName: $f->fileName($f->text('sick_leave.file'), $leave->code),
            badges: [$cancelled
                ? new DocumentBadge($f->text('sick_leave.cancelled'), DocumentTone::Danger)
                : new DocumentBadge($f->text('sick_leave.valid'), DocumentTone::Success)],
            highlights: [
                new DocumentFact($f->text('sick_leave.days_off'), $f->days($leave->days), tone: DocumentTone::Warning),
                new DocumentFact($f->text('sick_leave.from'), $f->date($leave->start_date), tone: DocumentTone::Info),
                new DocumentFact($f->text('sick_leave.to'), $f->date($leave->end_date), tone: DocumentTone::Violet),
                new DocumentFact($f->text('sick_leave.issued'), $f->date($leave->issued_at), tone: DocumentTone::Success),
            ],
            blocks: [
                ...($cancelled ? [new NoticeBlock($f->text('sick_leave.cancelled_notice', ['at' => $f->dateTime($leave->cancelled_at)]), (string) $leave->cancel_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('common.patient'), $f->patient($leave->patient, withCpr: true)),
                new FactsBlock($f->text('sick_leave.medical'), [
                    new DocumentFact($f->text('sick_leave.condition'), $disclosed ? (string) $leave->reason : $f->text('sick_leave.condition_private'), wide: true),
                    DocumentFact::optional($f->text('sick_leave.case'), $leave->notes, wide: true),
                    DocumentFact::optional($f->text('sick_leave.visit'), $leave->appointment?->code),
                ]),
                new TextBlock($f->text('sick_leave.certificate'), [
                    $f->text('sick_leave.greeting'),
                    $f->text('sick_leave.body', [
                        'name' => $person->full_name,
                        'id' => $person->national_id !== null
                            ? $f->text('sick_leave.id_cpr', ['value' => $person->national_id])
                            : $f->text('sick_leave.id_file', ['value' => $person->code]),
                        'clinic' => $this->clinic->current()->name,
                        'days' => $f->days($leave->days),
                        'from' => $f->date($leave->start_date),
                        'to' => $f->date($leave->end_date),
                        'reason' => $disclosed ? $f->text('sick_leave.because', ['reason' => (string) $leave->reason]) : $f->text('sick_leave.medical_reasons'),
                    ]),
                    $f->text('sick_leave.closing', ['date' => $f->date($leave->issued_at)]),
                ]),
                new SignatureBlock($leave->doctor->person->full_name, $leave->doctor->job_title, $leave->doctor_id),
            ],
            verifyNote: $f->text('sick_leave.verify'),
            issuedBy: $leave->doctor->person->full_name,
        );
    }
}
