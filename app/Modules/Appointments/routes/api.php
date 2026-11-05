<?php

use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\ClinicalOrderController;
use App\Http\Controllers\Appointments\ConsentController;
use App\Http\Controllers\Appointments\DoctorController;
use App\Http\Controllers\Appointments\MedicalRecordController;
use App\Http\Controllers\Appointments\PatientController;
use App\Http\Controllers\Appointments\PortalController;
use App\Http\Controllers\Appointments\RoomController;
use App\Http\Controllers\Appointments\ScheduleController;
use App\Http\Controllers\Appointments\SickLeaveController;
use App\Http\Controllers\Appointments\SpecialtyController;
use App\Http\Controllers\Appointments\TreatmentController;
use App\Http\Controllers\Documents\DocumentTemplateController;
use App\Http\Controllers\Documents\WrittenDocumentController;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsurePatient;
use App\Http\Middleware\EnsureStaff;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Appointments & Clinical API — Patients, Doctors & Specialties, Rooms, Schedules, Appointments,
| Treatments, Clinical orders, Medical records & Consents
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {

    // Patient portal: what patients change themselves (reads use the regular endpoints, limited to their own records)
    Route::prefix('portal')->name('portal.')->middleware(EnsurePatient::ALIAS)->controller(PortalController::class)->group(function (): void {
        Route::middleware('throttle:read')->group(function (): void {
            Route::get('profile', 'profile')->middleware('access:patients.read_own')->name('profile');
            Route::get('slots', 'slots')->middleware('access:appointments.create')->name('slots');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::put('profile', 'updateProfile')->middleware('access:patients.read_own')->name('profile.update');
            Route::post('appointments', 'book')->middleware(['access:appointments.create', EnsureIdempotency::ALIAS])->name('appointments.store');
            Route::patch('appointments/{appointment}/cancel', 'cancel')->whereUuid('appointment')->middleware('access:appointments.read_own&appointments.cancel')->name('appointments.cancel');
        });
    });

    // Patients
    Route::prefix('patients')->name('patients.')->controller(PatientController::class)->group(function (): void {
        Route::middleware(['access:patients.read_all|patients.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{patient}', 'show')->whereUuid('patient')->name('show');
            Route::get('{patient}/timeline', 'timeline')->whereUuid('patient')->name('timeline');
            Route::get('lookup', 'lookup')->middleware(EnsureStaff::ALIAS)->name('lookup');
        });
        // Break the glass (clinicians with medical rights) and the medical history kept by the care team.
        Route::post('{patient}/emergency-access', 'emergencyAccess')->whereUuid('patient')
            ->middleware(['access:patients.read_own&patients.view_medical', EnsureStaff::ALIAS, 'throttle:sensitive'])->name('emergency-access');
        Route::put('{patient}/medical-history', 'medicalHistory')->whereUuid('patient')
            ->middleware(['access:records.write&patients.view_history', 'throttle:write'])->name('medical-history');
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:patients.create')->name('store');
            Route::put('{patient}', 'update')->whereUuid('patient')->middleware('access:patients.write')->name('update');
        });
        Route::post('{patient}/portal-account', 'createPortalAccount')->whereUuid('patient')->middleware(['access:patients.create', 'throttle:sensitive'])->name('portal-account');
    });
    Route::dataTransfer('patients', 'patients');

    // Specialties (readable by every signed-in user: booking, price list)
    Route::prefix('doctors/specialties')->name('specialties.')->controller(SpecialtyController::class)->group(function (): void {
        Route::middleware('throttle:read')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{specialty}', 'show')->whereUuid('specialty')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:specialties.create')->name('store');
            Route::put('{specialty}', 'update')->whereUuid('specialty')->middleware('access:specialties.write')->name('update');
            Route::patch('{specialty}/archive', 'archive')->whereUuid('specialty')->middleware('access:specialties.write')->name('archive');
            Route::patch('{specialty}/unarchive', 'unarchive')->whereUuid('specialty')->middleware('access:specialties.write')->name('unarchive');
            Route::delete('{specialty}', 'destroy')->whereUuid('specialty')->middleware('access:specialties.delete')->name('destroy');
        });
        Route::patch('{specialty}/restore', 'restore')->whereUuid('specialty')->middleware(['access:specialties.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('doctors/specialties', 'specialties')->middleware('access:specialties.write|specialties.delete');
    Route::dataTransfer('doctors/specialties', 'specialties');

    // Doctors
    Route::prefix('doctors')->name('doctors.')->controller(DoctorController::class)->group(function (): void {
        Route::middleware(['access:doctors.read_all|doctors.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{doctor}', 'show')->whereUuid('doctor')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:doctors.create')->name('store');
            Route::put('{doctor}', 'update')->whereUuid('doctor')->middleware('access:doctors.write')->name('update');
            Route::patch('{doctor}/accepting', 'accepting')->whereUuid('doctor')->middleware('access:doctors.write')->name('accepting');
        });
    });
    Route::dataTransfer('doctors', 'doctors');

    // Rooms
    Route::prefix('rooms')->name('rooms.')->controller(RoomController::class)->group(function (): void {
        Route::middleware(['access:rooms.read_all|rooms.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{room}', 'show')->whereUuid('room')->name('show');
            Route::get('{room}/availability', 'availability')->whereUuid('room')->name('availability');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:rooms.create')->name('store');
            Route::put('{room}', 'update')->whereUuid('room')->middleware('access:rooms.write')->name('update');
            Route::patch('{room}/service', 'service')->whereUuid('room')->middleware('access:rooms.write')->name('service');
            Route::patch('{room}/archive', 'archive')->whereUuid('room')->middleware('access:rooms.write')->name('archive');
            Route::patch('{room}/unarchive', 'unarchive')->whereUuid('room')->middleware('access:rooms.write')->name('unarchive');
            Route::delete('{room}', 'destroy')->whereUuid('room')->middleware('access:rooms.delete')->name('destroy');
        });
        Route::patch('{room}/restore', 'restore')->whereUuid('room')->middleware(['access:rooms.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('rooms', 'rooms')->middleware('access:rooms.write|rooms.delete');
    Route::dataTransfer('rooms', 'rooms');

    // Provider schedules and clinic-wide holidays
    Route::controller(ScheduleController::class)->group(function (): void {
        Route::prefix('providers/{provider}/schedules')->whereUuid('provider')->name('schedules.')->group(function (): void {
            Route::middleware(['access:schedules.read_all|schedules.read_own', 'throttle:read'])->group(function (): void {
                Route::get('/', 'show')->name('show');
                Route::get('exceptions', 'exceptions')->name('exceptions.index');
            });
            Route::middleware('throttle:write')->group(function (): void {
                Route::put('/', 'replace')->middleware('access:schedules.write')->name('replace');
                Route::post('exceptions', 'addException')->middleware('access:schedules.create')->name('exceptions.store');
                Route::delete('exceptions/{exception}', 'removeException')->whereUuid('exception')->middleware('access:schedules.delete')->name('exceptions.destroy');
            });
        });
        Route::prefix('schedules/holidays')->name('holidays.')->group(function (): void {
            Route::get('/', 'holidays')->middleware('throttle:read')->name('index');
            Route::post('/', 'addHoliday')->middleware(['access:schedules.create&schedules.read_all', 'throttle:write'])->name('store');
            Route::delete('{exception}', 'removeHoliday')->whereUuid('exception')->middleware(['access:schedules.delete&schedules.read_all', 'throttle:write'])->name('destroy');
        });
    });

    // Appointments
    Route::prefix('appointments')->name('appointments.')->controller(AppointmentController::class)->group(function (): void {
        Route::middleware(['access:appointments.read_all|appointments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('calendar', 'calendar')->name('calendar');
            Route::get('available-slots', 'availableSlots')->name('available-slots');
            Route::get('providers', 'providers')->name('providers');
            Route::get('{appointment}', 'show')->whereUuid('appointment')->name('show');
            Route::get('{appointment}/history', 'history')->whereUuid('appointment')->name('history');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware(['access:appointments.create', EnsureStaff::ALIAS, EnsureIdempotency::ALIAS])->name('store');
            Route::patch('{appointment}/confirm', 'confirm')->whereUuid('appointment')->middleware('access:appointments.write')->name('confirm');
            Route::patch('{appointment}/check-in', 'checkIn')->whereUuid('appointment')->middleware('access:appointments.write&appointments.check_in')->name('check-in');
            Route::patch('{appointment}/start', 'start')->whereUuid('appointment')->middleware('access:appointments.write&appointments.treat')->name('start');
            Route::patch('{appointment}/complete', 'complete')->whereUuid('appointment')->middleware('access:appointments.write&appointments.treat')->name('complete');
            Route::patch('{appointment}/cancel', 'cancel')->whereUuid('appointment')->middleware('access:appointments.write&appointments.cancel')->name('cancel');
            Route::post('{appointment}/reschedule', 'reschedule')->whereUuid('appointment')->middleware('access:appointments.write')->name('reschedule');
            Route::patch('{appointment}/no-show', 'noShow')->whereUuid('appointment')->middleware('access:appointments.write')->name('no-show');
        });
    });
    Route::bulkActions('appointments', 'appointments')->middleware('access:appointments.write|appointments.delete');
    Route::dataTransfer('appointments', 'appointments');

    // Treatments (plans) and their sessions
    Route::prefix('treatments')->name('treatments.')->controller(TreatmentController::class)->group(function (): void {
        Route::middleware(['access:treatments.read_all|treatments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('queue', 'queue')->name('queue');
            Route::get('{treatment}', 'show')->whereUuid('treatment')->name('show');
            Route::get('{treatment}/sessions', 'sessions')->whereUuid('treatment')->name('sessions');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware(['access:treatments.create', EnsureIdempotency::ALIAS])->name('store');
            Route::post('{treatment}/sessions', 'bookSession')->whereUuid('treatment')->middleware(['access:appointments.create', EnsureStaff::ALIAS, EnsureIdempotency::ALIAS])->name('sessions.store');
            Route::put('{treatment}', 'update')->whereUuid('treatment')->middleware('access:treatments.write')->name('update');
            Route::patch('{treatment}/priority', 'changePriority')->whereUuid('treatment')->middleware('access:treatments.write')->name('priority');
            Route::patch('{treatment}/complete', 'complete')->whereUuid('treatment')->middleware('access:treatments.write')->name('complete');
            Route::patch('{treatment}/cancel', 'cancel')->whereUuid('treatment')->middleware('access:treatments.write')->name('cancel');
            Route::patch('{treatment}/archive', 'archive')->whereUuid('treatment')->middleware('access:treatments.write')->name('archive');
            Route::patch('{treatment}/unarchive', 'unarchive')->whereUuid('treatment')->middleware('access:treatments.write')->name('unarchive');
        });
        Route::middleware(['access:treatments.delete', 'throttle:sensitive'])->group(function (): void {
            Route::delete('{treatment}', 'destroy')->whereUuid('treatment')->name('destroy');
            Route::patch('{treatment}/restore', 'restore')->whereUuid('treatment')->name('restore');
        });
    });
    Route::bulkActions('treatments', 'treatments')->middleware('access:treatments.write|treatments.delete');
    Route::dataTransfer('treatments', 'treatments');

    // Clinical orders (imaging, lab, ECG) and referrals
    Route::prefix('clinical-orders')->name('clinical-orders.')->controller(ClinicalOrderController::class)->group(function (): void {
        Route::middleware(['access:orders.read_all|orders.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('worklist', 'worklist')->name('worklist');
            Route::get('{order}', 'show')->whereUuid('order')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware(['access:orders.create', EnsureIdempotency::ALIAS])->name('store');
            Route::put('{order}', 'update')->whereUuid('order')->middleware('access:orders.write')->name('update');
            Route::patch('{order}/start', 'start')->whereUuid('order')->middleware('access:orders.write&orders.perform')->name('start');
            Route::patch('{order}/complete', 'complete')->whereUuid('order')->middleware('access:orders.write&orders.perform')->name('complete');
            Route::patch('{order}/referral-appointment', 'linkReferralAppointment')->whereUuid('order')->middleware(['access:orders.write|appointments.create', EnsureStaff::ALIAS])->name('referral-appointment');
            Route::patch('{order}/cancel', 'cancel')->whereUuid('order')->middleware('access:orders.write')->name('cancel');
            Route::patch('{order}/archive', 'archive')->whereUuid('order')->middleware('access:orders.write')->name('archive');
            Route::patch('{order}/unarchive', 'unarchive')->whereUuid('order')->middleware('access:orders.write')->name('unarchive');
            Route::delete('{order}', 'destroy')->whereUuid('order')->middleware('access:orders.delete')->name('destroy');
        });
        Route::patch('{order}/restore', 'restore')->whereUuid('order')->middleware(['access:orders.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('clinical-orders', 'orders')->middleware('access:orders.write|orders.delete');
    Route::dataTransfer('clinical-orders', 'orders');

    // Medical records (one per appointment) and ICD-10 search
    Route::prefix('appointments/{appointment}/medical-record')->whereUuid('appointment')->name('medical-records.')->controller(MedicalRecordController::class)->group(function (): void {
        Route::get('/', 'show')->middleware(['access:records.read_all|records.read_own', 'throttle:read'])->name('show');
        Route::middleware(['access:records.write', 'throttle:write'])->group(function (): void {
            Route::put('/', 'save')->name('save');
            Route::post('sign', 'sign')->name('sign');
            Route::post('addenda', 'addAddendum')->name('addenda');
        });
    });
    Route::get('icd10', [MedicalRecordController::class, 'icd10'])->middleware(['access:records.read_all|records.read_own', 'throttle:read'])->name('icd10.search');

    // Sick leave certificates: issued at a visit by its doctor; patients, doctors and the front desk list and print them.
    Route::controller(SickLeaveController::class)->name('sick-leaves.')->group(function (): void {
        Route::middleware(['access:sick_leaves.read_all|sick_leaves.read_own', 'throttle:read'])->group(function (): void {
            Route::get('sick-leaves', 'index')->name('index');
            Route::get('sick-leaves/{sickLeave}', 'show')->whereUuid('sickLeave')->name('show');
        });
        Route::post('appointments/{appointment}/sick-leaves', 'store')->whereUuid('appointment')->middleware(['access:sick_leaves.create', 'throttle:write'])->name('store');
        Route::patch('sick-leaves/{sickLeave}/cancel', 'cancel')->whereUuid('sickLeave')->middleware(['access:sick_leaves.write', 'throttle:write'])->name('cancel');
    });

    // Document templates: ready-made (read-only, copy to change) and staff's own, in rich text with {{placeholders}}
    Route::prefix('document-templates')->name('document-templates.')->controller(DocumentTemplateController::class)->group(function (): void {
        Route::middleware(['access:templates.read_all|templates.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('placeholders', 'placeholders')->name('placeholders');
            Route::get('{template}', 'show')->whereUuid('template')->name('show');
        });
        Route::post('/', 'store')->middleware(['access:templates.create', 'throttle:write'])->name('store');
        Route::post('{template}/duplicate', 'duplicate')->whereUuid('template')->middleware(['access:templates.create', 'throttle:write'])->name('duplicate');
        Route::put('{template}', 'update')->whereUuid('template')->middleware(['access:templates.write', 'throttle:write'])->name('update');
        Route::delete('{template}', 'destroy')->whereUuid('template')->middleware(['access:templates.delete', 'throttle:write'])->name('destroy');
    });

    // Documents written from a visit session (reports, certificates, referral notes, letters) by its provider
    Route::controller(WrittenDocumentController::class)->name('written-documents.')->group(function (): void {
        Route::middleware(['access:written_documents.read_all|written_documents.read_own', 'throttle:read'])->group(function (): void {
            Route::get('written-documents', 'index')->name('index');
            Route::get('written-documents/{document}', 'show')->whereUuid('document')->name('show');
        });
        Route::middleware(['access:written_documents.create', 'throttle:write'])->group(function (): void {
            Route::post('appointments/{appointment}/written-documents/draft', 'draft')->whereUuid('appointment')->name('draft');
            Route::post('appointments/{appointment}/written-documents', 'store')->whereUuid('appointment')->name('store');
        });
    });

    // Patient consents
    Route::controller(ConsentController::class)->name('consents.')->group(function (): void {
        Route::get('patients/{patient}/consents', 'index')->whereUuid('patient')->middleware(['access:consents.read_all|consents.read_own', 'throttle:read'])->name('index');
        Route::post('patients/{patient}/consents', 'store')->whereUuid('patient')->middleware(['access:consents.create', 'throttle:write'])->name('store');
        Route::get('consents/{consent}', 'show')->whereUuid('consent')->middleware(['access:consents.read_all|consents.read_own', 'throttle:read'])->name('show');
        Route::patch('consents/{consent}/revoke', 'revoke')->whereUuid('consent')->middleware(['access:consents.write', 'throttle:write'])->name('revoke');
    });
});
