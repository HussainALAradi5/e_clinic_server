<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Enums\HR\StaffRequestType;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\HR\StaffRequest;
use App\Utilities\Documents\DocumentFormat;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Attendance slip of a staff request — a late excuse (late from the shift start until they arrived and were back at
 * work) or overtime (from the shift end until they left): the employee, the day, the exact from → to window, minutes,
 * the pay at stake, the reason given and the review, signed by the employee and the reviewer.
 */
final class AttendanceSlipDocument implements DocumentBuilder
{
    public function __construct(
        private readonly DocumentFormat $format,
        private readonly ClinicClock $clock,
    ) {}

    public function with(): array
    {
        return ['employee.person', 'employee.department', 'attendance', 'reviewer.person'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var StaffRequest $request */
        $request = $record;
        $f = $this->format;
        $employee = $request->employee;
        $day = $request->attendance;
        $late = $request->type === StaffRequestType::LateExcuse;
        $minutes = (int) ($request->details['minutes'] ?? ($late ? $day->late_minutes : $day->overtime_minutes));
        [$from, $to] = $late
            ? [$this->clock->combine($day->date, $employee->work_start_time), $day->check_in_at]
            : [$this->clock->combine($day->date, $employee->work_end_time), $day->check_out_at];
        $window = $f->time($from).' → '.$f->time($to);
        $amount = is_array($request->details['amount'] ?? null) ? MoneyHelper::of((string) $request->details['amount']['amount']) : null;

        return new DocumentPage(
            title: $f->text('attendance_slip.title', ['type' => $request->type->label()]),
            reference: $request->code,
            fileName: $f->fileName($request->type->label(), $request->code),
            badges: [$f->badge($request->type === StaffRequestType::LateExcuse ? $day->status : $request->status), $f->badge($request->status)],
            highlights: [
                new DocumentFact($f->text('attendance_slip.date'), $f->date($day->date), tone: DocumentTone::Info),
                new DocumentFact($f->text('attendance_slip.window'), $window, tone: DocumentTone::Violet),
                new DocumentFact($f->text('attendance_slip.minutes'), (string) $minutes, tone: $late ? DocumentTone::Warning : DocumentTone::Success),
                new DocumentFact($f->text('attendance_slip.status'), $request->status->label()),
            ],
            blocks: [
                new NoticeBlock($window, $f->text($late ? 'attendance_slip.late_window' : 'attendance_slip.overtime_window', [
                    'from' => $f->time($from), 'to' => $f->time($to), 'minutes' => $minutes,
                ]), $late ? DocumentTone::Warning : DocumentTone::Info),
                new FactsBlock($f->text('attendance_slip.employee'), [
                    new DocumentFact($f->text('common.name'), $employee->person->full_name),
                    new DocumentFact($f->text('attendance_slip.staff_no'), $employee->person->code),
                    new DocumentFact($f->text('attendance_slip.job'), $employee->job_title),
                    new DocumentFact($f->text('attendance_slip.department'), $employee->department->name),
                    new DocumentFact($f->text('attendance_slip.shift'), $f->clockTime($employee->work_start_time).' – '.$f->clockTime($employee->work_end_time)),
                ]),
                new FactsBlock($f->text('attendance_slip.day'), [
                    new DocumentFact($f->text('attendance_slip.check_in'), $f->time($day->check_in_at)),
                    new DocumentFact($f->text('attendance_slip.check_out'), $f->time($day->check_out_at)),
                    new DocumentFact($f->text('attendance_slip.worked'), $f->text('payslip.hours', ['h' => intdiv($day->worked_minutes, 60), 'm' => $day->worked_minutes % 60])),
                    DocumentFact::optional($f->text('attendance_slip.cost'), $amount ? $f->money($amount) : null),
                    DocumentFact::optional($f->text('attendance_slip.rate'), is_string($request->details['rate'] ?? null) ? $request->details['rate'] : null),
                ]),
                new TextBlock($f->text('attendance_slip.reason'), [$request->reason]),
                new FactsBlock($f->text('attendance_slip.review'), $request->reviewed_at === null
                    ? [new DocumentFact($f->text('attendance_slip.status'), $f->text('attendance_slip.not_reviewed'), wide: true)]
                    : [
                        new DocumentFact($f->text('attendance_slip.reviewed_by'), (string) $request->reviewer?->person->full_name),
                        new DocumentFact($f->text('attendance_slip.reviewed_at'), $f->dateTime($request->reviewed_at)),
                        DocumentFact::optional($f->text('attendance_slip.review_note'), $request->review_reason, wide: true),
                    ]),
                new SignatureBlock($employee->person->full_name, $employee->job_title, $employee->id, stamp: false),
                ...($request->reviewer ? [new SignatureBlock($request->reviewer->person->full_name, $f->text('attendance_slip.reviewed_by'), $request->reviewer->id)] : []),
            ],
            verifyNote: $f->text('attendance_slip.verify'),
            issuedBy: $request->reviewer?->person->full_name,
        );
    }
}
