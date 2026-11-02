<?php

use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Accounts\Models\AccountMapping;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Records\Models\MedicalRecord;
use App\Modules\Appointments\Records\Models\PatientConsent;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\Appointments\Schedules\Models\ProviderSchedule;
use App\Modules\Appointments\Schedules\Models\ScheduleException;
use App\Modules\Appointments\SickLeaves\Models\SickLeave;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Communication\Chat\Models\Conversation;
use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Employees\Models\EmployeeSalaryComponent;
use App\Modules\HR\Leaves\Models\LeaveRequest;
use App\Modules\HR\Leaves\Models\LeaveType;
use App\Modules\HR\Payroll\Models\PayrollRun;
use App\Modules\HR\Payroll\Models\Payslip;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Modules\HR\Settings\Models\Setting;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Reference\Addresses\Models\Address;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Reference\Countries\Models\Country;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Modules\Transactions\Expenses\Models\Expense;

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
        'notification' => Notification::class,
        'country' => Country::class,
        'address' => Address::class,
        'company' => Company::class,
        'account' => Account::class,
        'account_mapping' => AccountMapping::class,
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
