<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * What the event log can be opened for. Each case matches an alias in config `audit.models`, except PatientProfile,
 * which joins the patient row and the person it shares an id with.
 */
enum TrackedEntity: string implements HasLabel
{
    use EnumHelpers;

    case PatientProfile = 'patient_profile';
    case Person = 'person';
    case ChatMessage = 'chat_message';
    case Conversation = 'conversation';
    case SupportTicket = 'support_ticket';
    case User = 'user';
    case Role = 'role';
    case BackgroundTask = 'background_task';
    case Attachment = 'attachment';
    case GeneratedDocument = 'generated_document';
    case DocumentTemplate = 'document_template';
    case WrittenDocument = 'written_document';
    case Notification = 'notification';
    case Country = 'country';
    case Address = 'address';
    case Company = 'company';
    case ClinicProfile = 'clinic_profile';
    case Account = 'account';
    case AccountMapping = 'account_mapping';
    case FiscalYear = 'fiscal_year';
    case FiscalPeriod = 'fiscal_period';
    case JournalEntry = 'journal_entry';
    case Department = 'department';
    case Employee = 'employee';
    case SalaryComponent = 'salary_component';
    case Attendance = 'attendance';
    case LeaveType = 'leave_type';
    case LeaveRequest = 'leave_request';
    case PayrollRun = 'payroll_run';
    case StaffRequest = 'staff_request';
    case Setting = 'setting';
    case OvertimeBand = 'overtime_band';
    case Payslip = 'payslip';
    case Shift = 'shift';
    case Dashboard = 'dashboard';
    case BillableService = 'billable_service';
    case Patient = 'patient';
    case Specialty = 'specialty';
    case Doctor = 'doctor';
    case Room = 'room';
    case ProviderSchedule = 'provider_schedule';
    case ScheduleException = 'schedule_exception';
    case Appointment = 'appointment';
    case Treatment = 'treatment';
    case ClinicalOrder = 'clinical_order';
    case MedicalRecord = 'medical_record';
    case PatientConsent = 'patient_consent';
    case SickLeave = 'sick_leave';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case AppointmentFee = 'appointment_fee';
    case PatientPackage = 'patient_package';
    case ServicePackage = 'service_package';
    case Refund = 'refund';
    case InsuranceClaim = 'insurance_claim';
    case CashSession = 'cash_session';
    case Expense = 'expense';
    case Medication = 'medication';
    case MedicationBatch = 'medication_batch';
    case Prescription = 'prescription';
    case Dispensation = 'dispensation';

    /** The access-control resource whose `read_all` right opens this entity's event log. */
    private const RESOURCES = [
        'person' => 'people', 'chat_message' => 'chat', 'conversation' => 'chat', 'support_ticket' => 'tickets',
        'user' => 'users', 'role' => 'roles', 'background_task' => 'audit', 'attachment' => 'attachments',
        'generated_document' => 'templates', 'document_template' => 'templates', 'written_document' => 'written_documents',
        'notification' => 'notifications', 'country' => 'reference', 'address' => 'reference', 'company' => 'companies',
        'clinic_profile' => 'clinic_profile', 'account' => 'accounts', 'account_mapping' => 'accounts',
        'fiscal_year' => 'fiscal', 'fiscal_period' => 'fiscal', 'journal_entry' => 'journal',
        'department' => 'departments', 'employee' => 'employees', 'salary_component' => 'employees',
        'attendance' => 'attendance', 'leave_type' => 'leaves', 'leave_request' => 'leaves', 'payroll_run' => 'payroll',
        'staff_request' => 'staff_requests', 'setting' => 'settings', 'overtime_band' => 'settings', 'payslip' => 'payroll',
        'shift' => 'shifts', 'dashboard' => 'dashboards', 'billable_service' => 'services', 'patient' => 'patients',
        'specialty' => 'specialties', 'doctor' => 'doctors', 'room' => 'rooms', 'provider_schedule' => 'schedules',
        'schedule_exception' => 'schedules', 'appointment' => 'appointments', 'treatment' => 'treatments',
        'clinical_order' => 'orders', 'medical_record' => 'records', 'patient_consent' => 'consents',
        'sick_leave' => 'sick_leaves', 'invoice' => 'invoices', 'payment' => 'payments',
        'appointment_fee' => 'appointment_fees', 'patient_package' => 'patient_packages', 'service_package' => 'packages',
        'refund' => 'refunds', 'insurance_claim' => 'claims', 'cash_session' => 'cash_sessions', 'expense' => 'expenses',
        'medication' => 'medications', 'medication_batch' => 'inventory', 'prescription' => 'prescriptions',
        'dispensation' => 'dispensing',
    ];

    /**
     * The audit aliases stored for this entity.
     *
     * @return list<string>
     */
    public function auditableTypes(): array
    {
        return $this === self::PatientProfile ? [self::Person->value, self::Patient->value] : [$this->value];
    }

    /** Null for the patient file, which has its own right. */
    public function resource(): ?string
    {
        return self::RESOURCES[$this->value] ?? null;
    }

    /** Who may read this record's event log: it depends on the page, not on one right for the whole system. */
    public function readRight(): string
    {
        $resource = $this->resource();

        return $resource === null ? 'activity.read_all' : "{$resource}.read_all";
    }
}
