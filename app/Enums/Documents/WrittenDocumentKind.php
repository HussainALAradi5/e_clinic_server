<?php

declare(strict_types=1);

namespace App\Enums\Documents;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * What a document written at a visit is. It is written from the session by the visit's own provider: a doctor for
 * every kind; the nurse running a treatment session may also write an attendance certificate. (Sick leave has its own
 * record, issued by the doctor only.)
 */
enum WrittenDocumentKind: string implements HasLabel
{
    use EnumHelpers;

    case MedicalReport = 'medical_report';
    case AttendanceCertificate = 'attendance_certificate';
    case FitnessCertificate = 'fitness_certificate';
    case ReferralNote = 'referral_note';
    case GeneralLetter = 'general_letter';

    /** True when a nurse (the provider of a treatment session) may write it, not only a doctor. */
    public function nurseMayWrite(): bool
    {
        return $this === self::AttendanceCertificate;
    }
}
