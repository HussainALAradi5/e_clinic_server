<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * What an uploaded file is; each category has its own MIME types and size limit (config/attachments.php).
 */
enum AttachmentCategory: string implements HasLabel
{
    use EnumHelpers;

    case MedicalCertificate = 'medical_certificate';
    case XrayImage = 'xray_image';
    case LabResult = 'lab_result';
    case ClinicalDocument = 'clinical_document';
    case ConsentForm = 'consent_form';
    case ReferralLetter = 'referral_letter';
    case InsuranceDocument = 'insurance_document';
    case IdDocument = 'id_document';
    case InvoiceScan = 'invoice_scan';
    case Payslip = 'payslip';
    case ChatMedia = 'chat_media';
    case VoiceNote = 'voice_note';
    case TicketAttachment = 'ticket_attachment';
    case Other = 'other';
}
