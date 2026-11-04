<?php

declare(strict_types=1);

namespace App\Enums\Documents;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Fields a template can name as `{{patient_name}}`; filled from the visit when the document is generated. The editor
 * offers them in its "Insert field" menu.
 */
enum TemplatePlaceholder: string implements HasLabel
{
    use EnumHelpers;

    case PatientName = 'patient_name';
    case PatientCpr = 'patient_cpr';
    case PatientFileNo = 'patient_file_no';
    case PatientAge = 'patient_age';
    case PatientSex = 'patient_sex';
    case PatientBirthDate = 'patient_birth_date';
    case VisitDate = 'visit_date';
    case VisitTime = 'visit_time';
    case VisitReason = 'visit_reason';
    case Diagnosis = 'diagnosis';
    case AuthorName = 'author_name';
    case AuthorTitle = 'author_title';
    case ClinicName = 'clinic_name';
    case Today = 'today';

    /** As written in a template body. */
    public function token(): string
    {
        return '{{'.$this->value.'}}';
    }
}
