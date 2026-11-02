<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Appointments\ConsentType;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Appointments\TreatmentType;
use App\Enums\Payments\ServiceCategory;
use App\Enums\Reference\CompanyType;
use App\Enums\Shared\GenericStatus;
use App\Models\Accounting\Account;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Icd10Code;
use App\Models\Appointments\MedicalRecord;
use App\Models\Appointments\Patient;
use App\Models\Appointments\PatientConsent;
use App\Models\Appointments\ProviderSchedule;
use App\Models\Appointments\Room;
use App\Models\Appointments\Specialty;
use App\Models\Appointments\Treatment;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use App\Models\Reference\Company;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo data of the clinical work (Phase 3) — NEVER in production. Idempotent: skipped once treatments exist.
 * The nurse works as physiotherapist (Sun–Thu 08:00–16:00); three treatment plans (one in progress, one urgent
 * waiting in the queue, one with two missed sessions in a row), orders on every desk, signed visit notes with
 * ICD-10 diagnoses for past visits and a general consent for the portal patient.
 */
final class ClinicalDemoSeeder extends Seeder
{
    /** code => [name, category, price, revenue account, consent] */
    private const SERVICES = [
        'PT-SESSION' => ['Physiotherapy session', ServiceCategory::Procedure, '12.000', '4200', null],
        'ECG' => ['Electrocardiogram (ECG)', ServiceCategory::Radiology, '10.000', '4400', null],
        'US-ABD' => ['Abdominal ultrasound', ServiceCategory::Radiology, '25.000', '4400', null],
        'PROC-EXC' => ['Minor skin excision', ServiceCategory::Procedure, '35.000', '4200', ConsentType::Procedure],
    ];

    public function run(ClinicClock $clock): void
    {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Treatment::query()->exists()) {
            return;
        }
        $this->call(Icd10Seeder::class);
        $omar = Doctor::query()->whereHas('person', fn ($q) => $q->where('email', 'doctor@demo.eclinic.bh'))->first();
        $nurse = Person::query()->where('email', 'nurse@demo.eclinic.bh')->first();
        $admin = Person::query()->where('email', 'admin@demo.eclinic.bh')->first();
        if ($omar === null || $nurse === null || $admin === null) {
            return; // FrontDeskDemoSeeder runs first.
        }
        Auth::setUser(User::query()->findOrFail($admin->id));

        $services = $this->services();
        $this->therapistSchedule($nurse->id, $clock);
        $patients = Patient::query()->with('person')->orderBy('id')->get()->values();
        $portal = Patient::query()->whereHas('person', fn ($q) => $q->where('email', 'patient@demo.eclinic.bh'))->first() ?? $patients->first();
        if ($patients->count() < 6 || $portal === null) {
            return;
        }

        $this->treatments($omar, $nurse->id, $portal, array_values($patients->slice(1, 2)->all()), $services, $clock);
        $this->orders($omar, $nurse->id, array_values($patients->slice(3, 4)->all()), $services, $clock);
        // A released result for the portal patient (My results).
        ClinicalOrder::query()->create([
            'patient_id' => $portal->id, 'ordered_by' => $omar->id, 'type' => ClinicalOrderType::LabTest, 'billable_service_id' => $services['LAB-CBC']->id,
            'body_part' => 'Blood', 'status' => GenericStatus::Completed, 'priority' => TreatmentPriority::Medium, 'performed_by' => $nurse->id,
            'started_at' => $clock->now()->subDays(3), 'completed_at' => $clock->now()->subDays(3)->addMinutes(30), 'reported_by' => $omar->id, 'is_abnormal' => false,
            'clinical_notes' => 'Routine check before physiotherapy.', 'report' => 'Haemoglobin 14.2 g/dL, white cells 6.8 ×10⁹/L, platelets 245 ×10⁹/L. All within the normal range.',
        ]);
        $this->records($omar, $clock);
        PatientConsent::query()->create([
            'patient_id' => $portal->id, 'type' => ConsentType::GeneralTreatment, 'explained_by' => $omar->id,
            'description' => 'I agree to be examined and treated by the clinic staff. The usual tests and treatments were explained to me.',
            'signed_by_name' => $portal->person->full_name, 'signer_relationship' => 'self', 'signed_at' => $clock->now()->subMonths(2),
        ]);

        Auth::forgetUser();
    }

    /** @return array<string, BillableService> */
    private function services(): array
    {
        $made = [];
        foreach (self::SERVICES as $code => [$name, $category, $price, $account, $consent]) {
            $made[$code] = BillableService::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'price' => $price, 'tax_rate' => '0',
                'revenue_account_id' => Account::query()->where('code', $account)->value('id'), 'consent_type' => $consent,
            ]);
        }
        foreach (['XR-CHEST', 'LAB-CBC'] as $code) {
            $made[$code] = BillableService::query()->where('code', $code)->firstOrFail();
        }

        return $made;
    }

    private function therapistSchedule(string $nurseId, ClinicClock $clock): void
    {
        if (ProviderSchedule::query()->where('provider_id', $nurseId)->exists()) {
            return;
        }
        foreach ([0, 1, 2, 3, 4] as $day) {
            ProviderSchedule::query()->create(['provider_id' => $nurseId, 'day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'effective_from' => $clock->today()->subMonths(6)]);
        }
    }

    /**
     * @param  list<Patient>  $others
     * @param  array<string, BillableService>  $services
     */
    private function treatments(Doctor $doctor, string $therapist, Patient $portal, array $others, array $services, ClinicClock $clock): void
    {
        $gym = Room::query()->where('code', 'PT-1')->value('id');
        $treatmentRoom = Room::query()->where('code', 'TR-1')->value('id');
        // Physiotherapy belongs to the physiotherapist; the staff nurse gives the nursing treatments.
        $physio = (string) Person::query()->where('email', 'nurse2@demo.eclinic.bh')->value('id') ?: $therapist;
        $past = $this->workdays($clock->today(), -1, 3);
        $next = $this->workdays($clock->today(), 1, 1)[0];

        // In progress: attended, missed, attended — next session booked.
        $knee = $this->plan($portal, $doctor, TreatmentType::Physiotherapy, 'Knee physiotherapy', TreatmentPriority::Medium, 6, 45, $services['PT-SESSION'], GenericStatus::InProgress, $past[2]);
        $knee->forceFill(['diagnosis' => 'Patellofemoral pain syndrome, right knee', 'plan' => 'Quadriceps strengthening and stretching, twice a week; ice after exercise.'])->save();
        // The missed session's number (2) is reused by the next one.
        foreach ([[$past[2], 1, AppointmentStatus::Completed], [$past[1], 2, AppointmentStatus::NoShow], [$past[0], 2, AppointmentStatus::Completed], [$next, 3, AppointmentStatus::Confirmed]] as [$date, $number, $status]) {
            $this->session($knee, $physio, $gym, $date, '10:00', 45, $number, $status);
        }

        // Urgent, waiting in the scheduling queue.
        $this->plan($others[0], $doctor, TreatmentType::WoundCare, 'Post-operative wound care', TreatmentPriority::Urgent, 5, 20, BillableService::query()->firstWhere('code', 'PROC-DRESS'), GenericStatus::Pending, null);

        // Two missed sessions in a row: the attendance alert.
        $neb = $this->plan($others[1], $doctor, TreatmentType::RespiratoryTherapy, 'Nebuliser course', TreatmentPriority::High, 4, 30, BillableService::query()->firstWhere('code', 'PROC-NEB'), GenericStatus::Pending, null);
        $this->session($neb, $therapist, $treatmentRoom, $past[1], '12:00', 30, 1, AppointmentStatus::NoShow);
        $this->session($neb, $therapist, $treatmentRoom, $past[0], '12:00', 30, 1, AppointmentStatus::NoShow);
    }

    private function plan(Patient $patient, Doctor $doctor, TreatmentType $type, string $title, TreatmentPriority $priority, int $sessions, int $minutes, ?BillableService $service, GenericStatus $status, ?CarbonImmutable $start): Treatment
    {
        return Treatment::query()->create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => $type, 'title' => $title, 'priority' => $priority,
            'planned_sessions' => $sessions, 'session_duration_minutes' => $minutes, 'billable_service_id' => $service?->id,
            'status' => $status, 'start_date' => $start,
        ]);
    }

    private function session(Treatment $plan, string $therapist, ?string $roomId, CarbonImmutable $date, string $time, int $minutes, int $number, AppointmentStatus $status): void
    {
        $start = CarbonImmutable::parse($date->toDateString().' '.$time);
        Appointment::query()->create([
            'patient_id' => $plan->patient_id, 'provider_id' => $therapist, 'type' => AppointmentType::TreatmentSession,
            'treatment_id' => $plan->id, 'session_number' => $number, 'scheduled_date' => $date,
            'start_time' => $start->format('H:i:s'), 'end_time' => $start->addMinutes($minutes)->format('H:i:s'), 'room_id' => $roomId,
            'billable_service_id' => $plan->billable_service_id, 'status' => $status,
            'is_attended' => $status === AppointmentStatus::Completed, 'source' => AppointmentSource::Phone, 'reason' => $plan->title,
            'checked_in_at' => $status === AppointmentStatus::Completed ? $start->subMinutes(5) : null,
            'completed_at' => $status === AppointmentStatus::Completed ? $start->addMinutes($minutes) : null,
        ]);
    }

    /**
     * @param  list<Patient>  $patients
     * @param  array<string, BillableService>  $services
     */
    private function orders(Doctor $doctor, string $nurse, array $patients, array $services, ClinicClock $clock): void
    {
        $now = $clock->now();
        $order = fn (Patient $p, ClinicalOrderType $type, array $values) => ClinicalOrder::query()->create([
            'patient_id' => $p->id, 'ordered_by' => $doctor->id, 'type' => $type, 'status' => GenericStatus::Pending, 'priority' => TreatmentPriority::Medium, ...$values,
        ]);
        $room = fn (string $code): ?string => Room::query()->where('code', $code)->value('id');

        $order($patients[0], ClinicalOrderType::Xray, ['billable_service_id' => $services['XR-CHEST']->id, 'body_part' => 'Chest PA', 'priority' => TreatmentPriority::Urgent, 'clinical_notes' => 'Fever and productive cough for 10 days. Rule out pneumonia.']);
        $order($patients[1], ClinicalOrderType::LabTest, ['billable_service_id' => $services['LAB-CBC']->id, 'body_part' => 'Blood', 'status' => GenericStatus::InProgress, 'room_id' => $room('LAB-1'), 'performed_by' => $nurse, 'started_at' => $now->subMinutes(40), 'clinical_notes' => 'Fatigue, check for anaemia.']);
        $order($patients[2], ClinicalOrderType::Ecg, [
            'billable_service_id' => $services['ECG']->id, 'status' => GenericStatus::Completed, 'room_id' => $room('TR-1'), 'performed_by' => $nurse,
            'started_at' => $now->subDay(), 'completed_at' => $now->subDay()->addMinutes(15), 'reported_by' => $doctor->id, 'is_abnormal' => false,
            'clinical_notes' => 'Palpitations.', 'report' => 'Sinus rhythm 78/min. Normal axis and intervals. No ST changes.',
        ]);
        $order($patients[3], ClinicalOrderType::Xray, [
            'billable_service_id' => $services['XR-CHEST']->id, 'body_part' => 'Chest PA', 'status' => GenericStatus::Completed, 'room_id' => $room('X-1'),
            'started_at' => $now->subDay(), 'completed_at' => $now->subDay()->addMinutes(20), 'reported_by' => $doctor->id, 'is_abnormal' => true,
            'clinical_notes' => 'Persistent cough, smoker.', 'report' => 'Right lower lobe consolidation. Suggest antibiotics and follow-up film in 6 weeks.',
        ]);
        $order($patients[0], ClinicalOrderType::Referral, [
            'priority' => TreatmentPriority::High, 'referred_to_specialty_id' => Specialty::query()->where('code', 'PED')->value('id'),
            'clinical_notes' => 'Recurrent wheeze in a child; please assess for asthma.',
        ]);
        $order($patients[1], ClinicalOrderType::Referral, [
            'referred_to_company_id' => Company::query()->where('type', CompanyType::Hospital)->value('id'),
            'clinical_notes' => 'Suspected gallstones on history; please arrange ultrasound and surgical opinion.',
        ]);
    }

    /** Signed notes with diagnoses for Dr. Omar's past completed visits; vitals on today's visit in progress. */
    private function records(Doctor $doctor, ClinicClock $clock): void
    {
        $codes = Icd10Code::query()->whereIn('code', ['J06.9', 'I10', 'M54.5', 'E11.9', 'L20.9', 'R50.9'])->get()->keyBy('code');
        $notes = [
            ['Sore throat and runny nose for 3 days', 'Viral upper respiratory infection', 'Fluids, paracetamol, return if fever > 3 days', ['J06.9', 'R50.9']],
            ['Headache, BP check', 'Essential hypertension, controlled', 'Continue amlodipine 5 mg, recheck in 3 months', ['I10']],
            ['Lower back pain after lifting', 'Mechanical low back pain', 'Physiotherapy, ibuprofen 400 mg for 5 days', ['M54.5']],
            ['Diabetes review', 'Type 2 diabetes, HbA1c 7.4 %', 'Diet advice, continue metformin, HbA1c in 3 months', ['E11.9']],
        ];
        $visits = Appointment::query()->where('provider_id', $doctor->id)->where('status', AppointmentStatus::Completed)
            ->whereNull('treatment_id')->orderByDesc('scheduled_date')->orderBy('start_time')->limit(count($notes))->get();
        foreach ($visits as $i => $visit) {
            [$complaint, $assessment, $plan, $diagnoses] = $notes[$i];
            $record = MedicalRecord::query()->create([
                'id' => $visit->id, 'chief_complaint' => $complaint, 'assessment' => $assessment, 'plan' => $plan,
                'temperature_c' => '37.1', 'pulse_bpm' => 76 + $i, 'systolic_bp' => 128 + $i * 3, 'diastolic_bp' => 82, 'weight_kg' => 78 + $i, 'height_cm' => 172,
            ]);
            foreach ($diagnoses as $j => $code) {
                if (isset($codes[$code])) {
                    $record->diagnoses()->create(['icd10_code_id' => $codes[$code]->id, 'is_primary' => $j === 0]);
                }
            }
            $record->forceFill(['signed_by' => $doctor->id, 'signed_at' => $visit->endsAt()])->save();
        }

        $current = Appointment::query()->where('provider_id', $doctor->id)->where('status', AppointmentStatus::InProgress)->first();
        if ($current !== null) {
            MedicalRecord::query()->create(['id' => $current->id, 'temperature_c' => '38.2', 'pulse_bpm' => 96, 'systolic_bp' => 122, 'diastolic_bp' => 78, 'spo2_percent' => 97, 'weight_kg' => 70, 'height_cm' => 168]);
        }
    }

    /**
     * Clinic working days (Sunday–Thursday) going back (-1) or forward (+1) from a day, nearest first.
     *
     * @return list<CarbonImmutable>
     */
    private function workdays(CarbonImmutable $from, int $direction, int $count): array
    {
        $days = [];
        for ($d = $from->addDays($direction); count($days) < $count; $d = $d->addDays($direction)) {
            if (! in_array($d->dayOfWeek, [5, 6], true)) {
                $days[] = $d;
            }
        }

        return $days;
    }
}
