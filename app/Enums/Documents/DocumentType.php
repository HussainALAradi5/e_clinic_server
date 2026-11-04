<?php

declare(strict_types=1);

namespace App\Enums\Documents;

use App\Interfaces\Documents\DocumentBuilder;
use App\Interfaces\Shared\HasLabel;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\PatientConsent;
use App\Models\Appointments\SickLeave;
use App\Models\HR\Payslip;
use App\Models\HR\StaffRequest;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\Payment;
use App\Models\Pharmacy\Prescription;
use App\Models\Transactions\CashSession;
use App\Services\Documents\Builders\AttendanceSlipDocument;
use App\Services\Documents\Builders\ClaimDocument;
use App\Services\Documents\Builders\ConsentDocument;
use App\Services\Documents\Builders\InvoiceDocument;
use App\Services\Documents\Builders\PayslipDocument;
use App\Services\Documents\Builders\PrescriptionDocument;
use App\Services\Documents\Builders\ReceiptDocument;
use App\Services\Documents\Builders\ResultDocument;
use App\Services\Documents\Builders\SickLeaveDocument;
use App\Services\Documents\Builders\ZReportDocument;
use App\Traits\Shared\EnumHelpers;
use Illuminate\Database\Eloquent\Model;

/**
 * A record that can be downloaded as a PDF (`GET /documents/{type}/{id}`). Each type names its model, the permission
 * resource that guards it (`{resource}.read_all|{resource}.read_own`), the builder that lays it out, and the code
 * entity of its verification QR (null: no QR). Mirrored by the web and phone DocumentType enums.
 */
enum DocumentType: string implements HasLabel
{
    use EnumHelpers;

    case SickLeave = 'sick_leave';
    case Prescription = 'prescription';
    case Result = 'result';
    case Consent = 'consent';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case InsuranceClaim = 'insurance_claim';
    case Payslip = 'payslip';
    case ZReport = 'z_report';
    case AttendanceSlip = 'attendance_slip';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::SickLeave => SickLeave::class,
            self::Prescription => Prescription::class,
            self::Result => ClinicalOrder::class,
            self::Consent => PatientConsent::class,
            self::Invoice => Invoice::class,
            self::Receipt => Payment::class,
            self::InsuranceClaim => InsuranceClaim::class,
            self::Payslip => Payslip::class,
            self::ZReport => CashSession::class,
            self::AttendanceSlip => StaffRequest::class,
        };
    }

    public function resource(): string
    {
        return match ($this) {
            self::SickLeave => 'sick_leaves',
            self::Prescription => 'prescriptions',
            self::Result => 'orders',
            self::Consent => 'consents',
            self::Invoice => 'invoices',
            self::Receipt => 'payments',
            self::InsuranceClaim => 'claims',
            self::Payslip => 'payroll',
            self::ZReport => 'cash_sessions',
            self::AttendanceSlip => 'staff_requests',
        };
    }

    /** @return class-string<DocumentBuilder> */
    public function builder(): string
    {
        return match ($this) {
            self::SickLeave => SickLeaveDocument::class,
            self::Prescription => PrescriptionDocument::class,
            self::Result => ResultDocument::class,
            self::Consent => ConsentDocument::class,
            self::Invoice => InvoiceDocument::class,
            self::Receipt => ReceiptDocument::class,
            self::InsuranceClaim => ClaimDocument::class,
            self::Payslip => PayslipDocument::class,
            self::ZReport => ZReportDocument::class,
            self::AttendanceSlip => AttendanceSlipDocument::class,
        };
    }

    /** Alias in config/codes.php of the QR printed on the document; null prints none (e.g. a payslip). */
    public function codeEntity(): ?string
    {
        return match ($this) {
            self::SickLeave => 'sick_leave',
            self::Prescription => 'prescription',
            self::Result => 'clinical_order',
            self::Consent => 'patient_consent',
            self::Invoice => 'invoice',
            self::Receipt => 'payment',
            self::ZReport => 'cash_session',
            self::InsuranceClaim, self::Payslip, self::AttendanceSlip => null,
        };
    }

    /** The document a scanned code entity opens as (`clinical_order` → result); null when it has none. */
    public static function forCodeEntity(string $entity): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->codeEntity() === $entity) {
                return $type;
            }
        }

        return null;
    }
}
