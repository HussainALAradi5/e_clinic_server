<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use App\Console\Commands\Appointments\ImportIcd10Command;
use App\Dtos\Appointments\CancelAppointmentData;
use App\Enums\Appointments\CancelledBy;
use App\Events\Appointments\AppointmentBooked;
use App\Events\Appointments\AppointmentCancelled;
use App\Events\Appointments\AppointmentCompleted;
use App\Events\Appointments\AppointmentNoShow;
use App\Events\Appointments\AppointmentRescheduled;
use App\Events\Appointments\AppointmentStarted;
use App\Events\Appointments\ClinicalOrderCompleted;
use App\Events\Appointments\EmergencyAccessGranted;
use App\Events\Appointments\PatientCheckedIn;
use App\Events\Appointments\TreatmentAttendanceAlert;
use App\Events\Appointments\TreatmentCompleted;
use App\Events\Appointments\TreatmentCreated;
use App\Events\HR\EmployeeTerminated;
use App\Http\Resources\Appointments\AppointmentResource;
use App\Http\Resources\Appointments\ClinicalOrderResource;
use App\Http\Resources\Appointments\DoctorResource;
use App\Http\Resources\Appointments\PatientResource;
use App\Http\Resources\Appointments\RoomResource;
use App\Http\Resources\Appointments\SpecialtyResource;
use App\Http\Resources\Appointments\TreatmentResource;
use App\Imports\Appointments\PatientImport;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Appointments\ClinicalOrderServiceInterface;
use App\Interfaces\Appointments\ConsentServiceInterface;
use App\Interfaces\Appointments\DoctorServiceInterface;
use App\Interfaces\Appointments\MedicalRecordServiceInterface;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Interfaces\Appointments\RoomServiceInterface;
use App\Interfaces\Appointments\ScheduleServiceInterface;
use App\Interfaces\Appointments\SpecialtyServiceInterface;
use App\Interfaces\Appointments\TreatmentServiceInterface;
use App\Listeners\Appointments\CloseReferralOnVisit;
use App\Listeners\Appointments\NotifyEmergencyAccess;
use App\Listeners\Appointments\SendAppointmentNotifications;
use App\Listeners\Appointments\SendOrderNotifications;
use App\Listeners\Appointments\SendTreatmentNotifications;
use App\Listeners\Appointments\StopBookingsOfTerminatedDoctor;
use App\Listeners\Appointments\TrackTreatmentSessions;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Patient;
use App\Models\Appointments\Room;
use App\Models\Appointments\SickLeave;
use App\Models\Appointments\Specialty;
use App\Models\Appointments\Treatment;
use App\Services\Appointments\AppointmentService;
use App\Services\Appointments\ClinicalOrderService;
use App\Services\Appointments\ConsentService;
use App\Services\Appointments\DoctorService;
use App\Services\Appointments\MedicalRecordService;
use App\Services\Appointments\PatientService;
use App\Services\Appointments\RoomService;
use App\Services\Appointments\ScheduleService;
use App\Services\Appointments\SpecialtyService;
use App\Services\Appointments\TreatmentService;
use App\Services\Bulk\BulkActionRegistry;
use App\Utilities\Access\PermissionExpression;
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

        $bulk->register('sick_leaves', SickLeave::class, SickLeave::listQueryConfig(), genericActions: false);
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
