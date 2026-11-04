<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\MedicalRecord;
use App\Models\Appointments\Patient;
use App\Models\Appointments\PatientConsent;
use App\Models\Appointments\ProviderSchedule;
use App\Models\Appointments\Room;
use App\Models\Appointments\ScheduleException;
use App\Models\Appointments\SickLeave;
use App\Models\Appointments\Specialty;
use App\Models\Appointments\Treatment;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\Communication\SupportTicket;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\Setting;
use App\Models\HR\Shift;
use App\Models\HR\StaffRequest;
use App\Models\Identity\Attachment;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\GeneratedDocument;
use App\Models\Identity\Notification;
use App\Models\Identity\Person;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\Payment;
use App\Models\Payments\Refund;
use App\Models\Pharmacy\Dispensation;
use App\Models\Pharmacy\Medication;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\Prescription;
use App\Models\Reference\Address;
use App\Models\Reference\ClinicProfile;
use App\Models\Reference\Company;
use App\Models\Reference\Country;
use App\Models\Transactions\CashSession;
use App\Models\Transactions\Expense;

return [

    /*
    |--------------------------------------------------------------------------
    | Audit trail (Identity › Audit Logs)
    |--------------------------------------------------------------------------
    */

    /* Values replaced by `***` in old / new values (plus each model's auditMasked()). */
    'masked' => ['password', 'remember_token', 'token', 'two_factor_secret', 'two_factor_recovery_codes', 'iban', 'national_id', 'national_id_hash'],

    /* Changes to only these attributes do not create an audit row. */
    'ignored' => ['created_at', 'updated_at', 'version', 'created_by', 'updated_by', 'deleted_at', 'deleted_by'],

    /* Queue of the WriteAuditLog job (null = default queue). */
    'queue' => env('AUDIT_QUEUE'),

    /* Largest date range of one list query (days). */
    'max_range_days' => 93,

    /* Public morph aliases (Relation::morphMap) and `GET /audit-logs/{type}/{id}` types. Never a raw class name from the client. Modules add theirs. */
    'models' => [
        'person' => Person::class,
        'chat_message' => Message::class,
        'conversation' => Conversation::class,
        'support_ticket' => SupportTicket::class,
        'user' => User::class,
        'role' => Role::class,
        'background_task' => BackgroundTask::class,
        'attachment' => Attachment::class,
        'generated_document' => GeneratedDocument::class,
        'notification' => Notification::class,
        'country' => Country::class,
        'address' => Address::class,
        'company' => Company::class,
        'clinic_profile' => ClinicProfile::class,
        'account' => Account::class,
        'account_mapping' => AccountMapping::class,
        'fiscal_year' => FiscalYear::class,
        'fiscal_period' => FiscalPeriod::class,
        'journal_entry' => JournalEntry::class,
        'department' => Department::class,
        'employee' => Employee::class,
        'salary_component' => EmployeeSalaryComponent::class,
        'attendance' => Attendance::class,
        'leave_type' => LeaveType::class,
        'leave_request' => LeaveRequest::class,
        'payroll_run' => PayrollRun::class,
        'staff_request' => StaffRequest::class,
        'setting' => Setting::class,
        'payslip' => Payslip::class,
        'shift' => Shift::class,
        'billable_service' => BillableService::class,
        'patient' => Patient::class,
        'specialty' => Specialty::class,
        'doctor' => Doctor::class,
        'room' => Room::class,
        'provider_schedule' => ProviderSchedule::class,
        'schedule_exception' => ScheduleException::class,
        'appointment' => Appointment::class,
        'treatment' => Treatment::class,
        'clinical_order' => ClinicalOrder::class,
        'medical_record' => MedicalRecord::class,
        'patient_consent' => PatientConsent::class,
        'sick_leave' => SickLeave::class,
        'invoice' => Invoice::class,
        'payment' => Payment::class,
        'refund' => Refund::class,
        'insurance_claim' => InsuranceClaim::class,
        'cash_session' => CashSession::class,
        'expense' => Expense::class,
        'medication' => Medication::class,
        'medication_batch' => MedicationBatch::class,
        'prescription' => Prescription::class,
        'dispensation' => Dispensation::class,
    ],

];
