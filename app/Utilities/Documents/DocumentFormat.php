<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Enums\Documents\DocumentTone;
use App\Enums\Shared\Gender;
use App\Interfaces\Shared\HasLabel;
use App\Models\Appointments\Patient;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use BackedEnum;
use Brick\Money\Money;
use Carbon\CarbonInterface;

/**
 * Wording shared by the document builders: text from lang/en/documents.php, clinic-local dates and times, money,
 * status badges and the patient card.
 */
final class DocumentFormat
{
    private const DATE = 'j M Y';

    private const DATE_TIME = 'j M Y, H:i';

    private const TIME = 'H:i';

    /** Status values read as good, bad or waiting, whatever enum they come from. */
    private const TONES = [
        'completed' => DocumentTone::Success, 'paid' => DocumentTone::Success, 'approved' => DocumentTone::Success,
        'closed' => DocumentTone::Success, 'dispensed' => DocumentTone::Success, 'present' => DocumentTone::Success,
        'cancelled' => DocumentTone::Danger, 'voided' => DocumentTone::Danger, 'rejected' => DocumentTone::Danger,
        'overdue' => DocumentTone::Danger, 'refunded' => DocumentTone::Danger, 'absent' => DocumentTone::Danger,
        'pending' => DocumentTone::Warning, 'unpaid' => DocumentTone::Warning, 'partially_paid' => DocumentTone::Warning,
        'late' => DocumentTone::Warning,
        'in_progress' => DocumentTone::Info, 'issued' => DocumentTone::Info, 'open' => DocumentTone::Info, 'closing' => DocumentTone::Info,
    ];

    public function __construct(private readonly ClinicClock $clock) {}

    /**
     * Text of lang/en/documents.php, e.g. text('sick_leave.title').
     *
     * @param  array<string, string|int>  $replace
     */
    public function text(string $key, array $replace = []): string
    {
        return (string) __('documents.'.$key, $replace);
    }

    /**
     * A list of texts (table column headings).
     *
     * @return list<string>
     */
    public function columns(string $key): array
    {
        /** @var list<string> */
        return (array) __('documents.'.$key);
    }

    /** A calendar date (stored as a date, shown as is). */
    public function date(?CarbonInterface $date): string
    {
        return $date?->format(self::DATE) ?? '—';
    }

    /** A moment (stored in UTC), shown in clinic time. */
    public function dateTime(?CarbonInterface $at): string
    {
        return $at === null ? '—' : $this->clock->toClinic($at)->format(self::DATE_TIME);
    }

    /** Time of day in clinic time ("08:35"). */
    public function time(?CarbonInterface $at): string
    {
        return $at === null ? '—' : $this->clock->toClinic($at)->format(self::TIME);
    }

    public function now(): string
    {
        return $this->clock->now()->format(self::DATE_TIME);
    }

    public function money(?Money $money): string
    {
        return $money === null ? '—' : MoneyHelper::format($money);
    }

    public function badge(BackedEnum&HasLabel $status): DocumentBadge
    {
        return new DocumentBadge($status->label(), self::TONES[(string) $status->value] ?? DocumentTone::Neutral);
    }

    /** "1 day", "3 days". */
    public function days(int $days): string
    {
        return trans_choice('documents.common.days', $days, ['count' => $days]);
    }

    /**
     * Name, CPR (when asked), file number, date of birth with age, sex (coloured badge) and phone of a patient.
     *
     * @return list<DocumentFact|null>
     */
    public function patient(Patient $patient, bool $withCpr = false): array
    {
        $person = $patient->person;

        return [
            new DocumentFact($this->text('common.name'), $person->full_name),
            $withCpr ? DocumentFact::optional($this->text('common.cpr'), $person->national_id) : null,
            new DocumentFact($this->text('common.file_no'), $person->code),
            new DocumentFact($this->text('common.born'), $this->text('common.age', ['date' => $this->date($person->date_of_birth), 'age' => $person->date_of_birth->age])),
            new DocumentFact($this->text('common.sex'), $person->gender->label(), tone: $person->gender === Gender::Female ? DocumentTone::Rose : DocumentTone::Info),
            new DocumentFact($this->text('common.phone'), $person->phone),
        ];
    }

    /** Safe file name: "Sick leave SLV-2026-000001.pdf". */
    public function fileName(string $title, string $reference): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9 ._-]+/', '', $title.' '.$reference)).'.pdf';
    }
}
