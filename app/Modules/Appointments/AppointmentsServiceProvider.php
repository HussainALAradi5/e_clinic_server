<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use App\Enums\Appointments\CancelledBy;
use App\Modules\Appointments\Appointments\Contracts\AppointmentServiceInterface;
use App\Modules\Appointments\Appointments\Data\CancelAppointmentData;
use App\Modules\Appointments\Appointments\Events\AppointmentBooked;
use App\Modules\Appointments\Appointments\Events\AppointmentCancelled;
use App\Modules\Appointments\Appointments\Events\AppointmentCompleted;
use App\Modules\Appointments\Appointments\Events\AppointmentNoShow;
use App\Modules\Appointments\Appointments\Events\AppointmentRescheduled;
use App\Modules\Appointments\Appointments\Events\AppointmentStarted;
use App\Modules\Appointments\Appointments\Events\PatientCheckedIn;
use App\Modules\Appointments\Appointments\Http\Resources\AppointmentResource;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Appointments\Services\AppointmentService;
use App\Modules\Appointments\Doctors\Contracts\DoctorServiceInterface;
use App\Modules\Appointments\Doctors\Contracts\SpecialtyServiceInterface;
use App\Modules\Appointments\Doctors\Http\Resources\DoctorResource;
use App\Modules\Appointments\Doctors\Http\Resources\SpecialtyResource;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Modules\Appointments\Doctors\Services\DoctorService;
use App\Modules\Appointments\Doctors\Services\SpecialtyService;
use App\Modules\Appointments\Listeners\SendAppointmentNotifications;
use App\Modules\Appointments\Listeners\StopBookingsOfTerminatedDoctor;
use App\Modules\Appointments\Orders\Contracts\ClinicalOrderServiceInterface;
use App\Modules\Appointments\Orders\Events\ClinicalOrderCompleted;
use App\Modules\Appointments\Orders\Http\Resources\ClinicalOrderResource;
use App\Modules\Appointments\Orders\Listeners\CloseReferralOnVisit;
use App\Modules\Appointments\Orders\Listeners\SendOrderNotifications;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Orders\Services\ClinicalOrderService;
use App\Modules\Appointments\Patients\Contracts\PatientServiceInterface;
use App\Modules\Appointments\Patients\Events\EmergencyAccessGranted;
use App\Modules\Appointments\Patients\Http\Resources\PatientResource;
use App\Modules\Appointments\Patients\Imports\PatientImport;
use App\Modules\Appointments\Patients\Listeners\NotifyEmergencyAccess;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Patients\Services\PatientService;
use App\Modules\Appointments\Records\Console\ImportIcd10Command;
use App\Modules\Appointments\Records\Contracts\ConsentServiceInterface;
use App\Modules\Appointments\Records\Contracts\MedicalRecordServiceInterface;
use App\Modules\Appointments\Records\Services\ConsentService;
use App\Modules\Appointments\Records\Services\MedicalRecordService;
use App\Modules\Appointments\Rooms\Contracts\RoomServiceInterface;
use App\Modules\Appointments\Rooms\Http\Resources\RoomResource;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\Appointments\Rooms\Services\RoomService;
use App\Modules\Appointments\Schedules\Contracts\ScheduleServiceInterface;
use App\Modules\Appointments\Schedules\Services\ScheduleService;
use App\Modules\Appointments\Treatments\Contracts\TreatmentServiceInterface;
use App\Modules\Appointments\Treatments\Events\TreatmentAttendanceAlert;
use App\Modules\Appointments\Treatments\Events\TreatmentCompleted;
use App\Modules\Appointments\Treatments\Events\TreatmentCreated;
use App\Modules\Appointments\Treatments\Http\Resources\TreatmentResource;
use App\Modules\Appointments\Treatments\Listeners\SendTreatmentNotifications;
use App\Modules\Appointments\Treatments\Listeners\TrackTreatmentSessions;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Appointments\Treatments\Services\TreatmentService;
use App\Modules\HR\Employees\Events\EmployeeTerminated;
use App\Shared\Access\PermissionExpression;
use App\Shared\Bulk\BulkActionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Appointments & Clinical module — front desk (patients, doctors & specialties, rooms, provider schedules,
 * appointments) and clinical work (treatment plans and sessions, clinical orders & referrals, medical records,
 * ICD-10 diagnoses and patient consents).
 */
final class AppointmentsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        PatientServiceInterface::class => PatientService::class,
        SpecialtyServiceInterface::class => SpecialtyService::class,
        DoctorServiceInterface::class => DoctorService::class,
        RoomServiceInterface::class => RoomService::class,
        ScheduleServiceInterface::class => ScheduleService::class,
        AppointmentServiceInterface::class => AppointmentService::class,
        TreatmentServiceInterface::class => TreatmentService::class,
        ClinicalOrderServiceInterface::class => ClinicalOrderService::class,
        MedicalRecordServiceInterface::class => MedicalRecordService::class,
        ConsentServiceInterface::class => ConsentService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen(EmployeeTerminated::class, StopBookingsOfTerminatedDoctor::class);
        Event::listen(EmergencyAccessGranted::class, NotifyEmergencyAccess::class);
        Event::listen([AppointmentBooked::class, AppointmentCancelled::class, AppointmentRescheduled::class], SendAppointmentNotifications::class);
        Event::listen([PatientCheckedIn::class, AppointmentStarted::class, AppointmentCompleted::class, AppointmentNoShow::class], TrackTreatmentSessions::class);
        Event::listen([TreatmentCreated::class, TreatmentAttendanceAlert::class, TreatmentCompleted::class], SendTreatmentNotifications::class);
        Event::listen(AppointmentCompleted::class, CloseReferralOnVisit::class);
        Event::listen(ClinicalOrderCompleted::class, SendOrderNotifications::class);
        $this->commands([ImportIcd10Command::class]);

        $withPerson = fn (string $table): \Closure => fn (Builder $q) => $q->select("{$table}.*")
            ->join('persons', 'persons.id', '=', "{$table}.id")->where('persons.is_deleted', false);

        $bulk->register('patients', Patient::class, Patient::listQueryConfig())
            ->exportable(PatientResource::class, fn (Builder $q) => $withPerson('patients')($q)->with(['person', 'insurer']), values: [
                'full_name' => fn (Model $p): string => $p instanceof Patient ? $p->person->full_name : '',
                'insurer' => fn (Model $p): string => $p instanceof Patient ? (string) $p->insurer?->name : '',
            ])
            ->importable(PatientImport::class);

        $bulk->register('specialties', Specialty::class, Specialty::listQueryConfig(), SpecialtyService::class)
            ->readableBy(PermissionExpression::AUTHENTICATED)
            ->exportable(SpecialtyResource::class, fn (Builder $q) => $q->withCount('doctors'));

        $bulk->register('doctors', Doctor::class, Doctor::listQueryConfig())
            ->exportable(DoctorResource::class, fn (Builder $q) => $withPerson('doctors')($q)->with(DoctorService::RELATIONS), values: [
                'specialty' => fn (Model $d): string => $d instanceof Doctor ? $d->specialty->name : '',
            ]);

        $bulk->register('rooms', Room::class, Room::listQueryConfig(), RoomService::class)
            ->exportable(RoomResource::class, fn (Builder $q) => $q->with('department'), values: [
                'department' => fn (Model $r): string => $r instanceof Room ? (string) $r->department?->name : '',
            ]);

        $bulk->register('appointments', Appointment::class, Appointment::listQueryConfig(), genericActions: false)
            ->action('confirm', 'appointments.write', [AppointmentServiceInterface::class, 'confirm'])
            ->action('cancel', 'appointments.cancel', fn (Model $a, ?string $reason): mixed => $a instanceof Appointment
                ? app(AppointmentServiceInterface::class)->cancel($a, new CancelAppointmentData(CancelledBy::Clinic, (string) $reason))
                : null, needsReason: true)
            ->exportable(AppointmentResource::class, fn (Builder $q) => $q->with(AppointmentService::RELATIONS), values: [
                'patient' => fn (Model $a): string => $a instanceof Appointment ? $a->patient->person->full_name : '',
                'provider' => fn (Model $a): string => $a instanceof Appointment ? $a->provider->person->full_name : '',
                'room' => fn (Model $a): string => $a instanceof Appointment ? (string) $a->room?->name : '',
            ]);

        $bulk->register('treatments', Treatment::class, Treatment::listQueryConfig(), TreatmentService::class)
            ->exportable(TreatmentResource::class, fn (Builder $q) => $q->with(TreatmentService::RELATIONS), values: [
                'patient' => fn (Model $t): string => $t instanceof Treatment ? $t->patient->person->full_name : '',
                'doctor' => fn (Model $t): string => $t instanceof Treatment ? $t->doctor->person->full_name : '',
                'progress' => fn (Model $t): string => $t instanceof Treatment ? app(TreatmentServiceInterface::class)->progress($t)->attended.' / '.$t->planned_sessions : '',
            ]);

        $bulk->register('orders', ClinicalOrder::class, ClinicalOrder::listQueryConfig(), ClinicalOrderService::class)
            ->exportable(ClinicalOrderResource::class, fn (Builder $q) => $q->with(ClinicalOrderService::RELATIONS), values: [
                'patient' => fn (Model $o): string => $o instanceof ClinicalOrder ? $o->patient->person->full_name : '',
                'ordered_by' => fn (Model $o): string => $o instanceof ClinicalOrder ? $o->orderedBy->person->full_name : '',
            ]);
    }
}
