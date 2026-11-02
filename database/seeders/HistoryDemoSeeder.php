<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Appointments\TreatmentType;
use App\Enums\Payments\InvoiceStatus;
use App\Enums\Pharmacy\MedicationForm;
use App\Enums\Pharmacy\MedicationRoute;
use App\Enums\Transactions\ExpenseCategory;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Records\Models\Icd10Code;
use App\Modules\Appointments\Records\Models\MedicalRecord;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\Appointments\Schedules\Models\ProviderSchedule;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Leaves\Contracts\LeaveServiceInterface;
use App\Modules\HR\Leaves\Data\LeaveRequestData;
use App\Modules\HR\Leaves\Models\LeaveType;
use App\Modules\HR\Support\WorkCalendar;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Payments\Claims\Contracts\InsuranceClaimServiceInterface;
use App\Modules\Payments\Claims\Data\ClaimDecisionData;
use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Payments\Invoices\Exceptions\AlreadyChargedException;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Payments\Contracts\PaymentServiceInterface;
use App\Modules\Payments\Payments\Data\RecordPaymentData;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Refunds\Contracts\RefundServiceInterface;
use App\Modules\Payments\Refunds\Data\RefundRequestData;
use App\Modules\Pharmacy\Dispensing\Contracts\DispensingServiceInterface;
use App\Modules\Pharmacy\Dispensing\Data\DispenseData;
use App\Modules\Pharmacy\Dispensing\Data\DispenseItemData;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Data\ReceiveStockData;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Prescriptions\Contracts\PrescriptionServiceInterface;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionData;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionItemData;
use App\Modules\Pharmacy\Prescriptions\Models\PrescriptionItem;
use App\Modules\Pharmacy\Support\Qty;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Modules\Transactions\CashSessions\Data\CloseSessionData;
use App\Modules\Transactions\CashSessions\Data\DepositData;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Modules\Transactions\Expenses\Contracts\ExpenseServiceInterface;
use App\Modules\Transactions\Expenses\Data\ExpenseData;
use App\Modules\Transactions\Expenses\Data\PayExpenseData;
use App\Shared\Enums\GenericStatus;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * About ten weeks of clinic life — NEVER in production. Runs last; idempotent (skipped once cashier2 has a drawer).
 *
 * Every working day from the start of the window to three weeks ahead, each doctor sees patients from their own
 * schedule: past visits are completed (some no-shows and cancellations) with signed notes and diagnoses, tests and
 * scans with reports, prescriptions dispensed at the pharmacy; treatment plans run their sessions with the
 * physiotherapists. Money goes through the real services day by day — charged, issued, paid in cash / card /
 * BenefitPay / bank transfer (some partly, some still owing), refunds, a drawer per day banked the next morning,
 * insurance claims submitted and decided, and the month's expenses. Upcoming weeks are booked (confirmed, pending
 * online requests, a few cancelled).
 */
final class HistoryDemoSeeder extends Seeder
{
    /** Calendar days of history (shortened at the start of the fiscal year). */
    private const HISTORY_DAYS = 70;

    private const FUTURE_DAYS = 21;

    /** Specialty code => [visit reasons, diagnoses (ICD-10), orders (service codes)] */
    private const CARE = [
        'GP' => [['Fever and cough', 'Sore throat', 'Headache', 'Stomach ache', 'Routine check-up', 'Tiredness', 'Ear pain'],
            ['J06.9', 'J02.9', 'J20.9', 'G44.2', 'K29.70', 'A09', 'H66.9', 'I10', 'E11.9', 'E78.5'], ['LAB-CBC', 'LAB-HBA1C', 'LAB-LIPID', 'XR-CHEST', 'LAB-UA']],
        'PED' => [['Fever', 'Cough and wheeze', 'Vaccination', 'Rash', 'Diarrhoea', 'Ear pain', 'Growth check'],
            ['J06.9', 'J45.909', 'A09', 'H66.9', 'L20.9', 'J00', 'B34.9'], ['LAB-CBC', 'LAB-UA', 'XR-CHEST']],
        'IM' => [['Diabetes review', 'High blood pressure', 'Chest discomfort', 'Thyroid follow-up', 'Fatigue', 'Reflux'],
            ['E11.9', 'E11.65', 'I10', 'E03.9', 'E78.5', 'K21.9', 'D50.9', 'E55.9'], ['LAB-HBA1C', 'LAB-LIPID', 'LAB-TSH', 'LAB-VITD', 'LAB-CBC', 'ECG']],
        'DERM' => [['Acne', 'Itchy rash', 'Warts', 'Hair loss', 'Eczema flare', 'Hives'],
            ['L70.0', 'L20.9', 'L23.9', 'L50.9', 'B35.3'], ['LAB-CBC', 'LAB-VITD']],
        'ORTHO' => [['Knee pain', 'Back pain', 'Shoulder pain', 'Sports injury', 'Neck pain', 'Wrist pain'],
            ['M25.561', 'M25.562', 'M54.5', 'M54.2', 'M25.511', 'M17.11', 'G56.0'], ['XR-KNEE', 'XR-SPINE', 'LAB-VITD']],
        'CARD' => [['Palpitations', 'Chest pain on exertion', 'Hypertension review', 'Breathlessness', 'Pre-operative check'],
            ['I10', 'I20.9', 'I48.91', 'I25.10', 'R07.9', 'E78.5'], ['ECG', 'LAB-LIPID', 'US-ABD']],
        'DENT' => [['Toothache', 'Check-up and cleaning', 'Bleeding gums', 'Broken filling', 'Sensitive teeth', 'Wisdom tooth pain'],
            ['K02.9', 'K04.0', 'K04.7', 'K05.10', 'K05.6', 'K03.6', 'K01.1'], ['XR-DENT']],
    ];

    /** Reports by order kind, normal and abnormal. */
    private const REPORTS = [
        'LAB-CBC' => ['Haemoglobin 13.8 g/dL, white cells 7.1, platelets 260. Normal.', 'Haemoglobin 9.6 g/dL, MCV 71 fL — microcytic anaemia, check iron studies.'],
        'LAB-HBA1C' => ['HbA1c 5.6 %. Normal.', 'HbA1c 8.3 % — above target; adjust treatment.'],
        'LAB-LIPID' => ['Total cholesterol 4.6, LDL 2.7, HDL 1.3 mmol/L. Within target.', 'LDL 4.4 mmol/L — raised; lifestyle advice and statin discussed.'],
        'LAB-TSH' => ['TSH 2.1 mIU/L. Normal.', 'TSH 7.8 mIU/L — subclinical hypothyroidism; repeat in 6 weeks.'],
        'LAB-VITD' => ['25-OH vitamin D 34 ng/mL. Sufficient.', '25-OH vitamin D 11 ng/mL — deficient; start supplements.'],
        'LAB-UA' => ['No leucocytes, nitrites or blood. Normal.', 'Leucocytes ++, nitrites positive — urinary tract infection.'],
        'XR-CHEST' => ['Clear lung fields, normal heart size.', 'Patchy right lower zone opacity — likely pneumonia.'],
        'XR-KNEE' => ['No fracture; joint spaces preserved.', 'Medial joint space narrowing and osteophytes — osteoarthritis.'],
        'XR-SPINE' => ['Normal alignment, no fracture.', 'Reduced L4–L5 disc height with osteophytes.'],
        'ECG' => ['Sinus rhythm 72/min, normal axis and intervals.', 'Atrial fibrillation, ventricular rate 104/min.'],
        'US-ABD' => ['Normal liver, gallbladder, kidneys and spleen.', 'Fatty liver; gallbladder stones without wall thickening.'],
        'XR-DENT' => ['Panoramic (OPG): no caries or bone loss; third molars erupted.', 'Panoramic (OPG): deep caries 36 with periapical radiolucency; impacted 48.'],
    ];

    /** Prescriptions by specialty: [medication code, quantity, dose, frequency, days, route] */
    private const RX = [
        'GP' => [['MED-PARA-500', '20', '1–2 tablets', 'Every 6 hours as needed', 5, 'oral'], ['MED-AMOX-500', '21', '1 capsule', '3 times daily', 7, 'oral'], ['MED-IBU-400', '15', '1 tablet', '3 times daily after meals', 5, 'oral']],
        'PED' => [['MED-PARA-500', '10', 'Half a tablet', 'Every 6 hours as needed', 3, 'oral'], ['MED-ORS', '4', '1 sachet in 200 ml water', 'After each loose stool', 2, 'oral'], ['MED-SALB-100', '1', '2 puffs with spacer', 'When wheezy', 30, 'inhalation']],
        'IM' => [['MED-OMEP-20', '28', '1 capsule', 'Once daily before breakfast', 28, 'oral'], ['MED-PARA-500', '20', '1 tablet', 'Every 6 hours as needed', 5, 'oral']],
        'DERM' => [['MED-FUSI-2', '1', 'Thin layer', 'Twice daily', 7, 'topical']],
        'ORTHO' => [['MED-IBU-400', '20', '1 tablet', '3 times daily after meals', 7, 'oral'], ['MED-PARA-500', '20', '1–2 tablets', 'Every 6 hours as needed', 5, 'oral']],
        'CARD' => [['MED-AZIT-250', '6', '2 tablets on day 1, then 1', 'Once daily', 5, 'oral']],
        'DENT' => [['MED-AMOX-500', '15', '1 capsule', '3 times daily', 5, 'oral'], ['MED-IBU-400', '10', '1 tablet', '3 times daily after meals', 3, 'oral']],
    ];

    private ClinicClock $clock;

    /** The real today and now (the clock moves through the history while seeding). */
    private CarbonImmutable $today;

    private CarbonImmutable $now;

    /** @var Collection<int, Patient> */
    private Collection $adults;

    /** @var Collection<int, Patient> */
    private Collection $children;

    /** @var array<string, User> */
    private array $users = [];

    /** @var Collection<int, Patient> the portal accounts (patient, patient2, patient3), seen more often */
    private Collection $portal;

    public function run(
        ClinicClock $clock,
        InvoiceServiceInterface $invoices,
        PaymentServiceInterface $payments,
        RefundServiceInterface $refunds,
        CashSessionServiceInterface $drawers,
        InsuranceClaimServiceInterface $claims,
        ExpenseServiceInterface $expenses,
        PrescriptionServiceInterface $prescriptions,
        DispensingServiceInterface $dispensing,
        InventoryServiceInterface $inventory,
        LeaveServiceInterface $leaves,
        WorkCalendar $calendar,
    ): void {
        if (app()->isProduction() && ! config('app.seed_demo')) {
            return;
        }
        foreach (['admin', 'accountant', 'cashier2', 'reception', 'reception2', 'pharmacist', 'nurse', 'nurse2', 'hr'] as $name) {
            $user = User::query()->whereHas('person', fn ($q) => $q->where('email', $name.DemoSeeder::EMAIL_DOMAIN))->first();
            if ($user === null) {
                return; // DemoSeeder and TeamDemoSeeder run first.
            }
            $this->users[$name] = $user;
        }
        if (CashSession::query()->where('cashier_id', $this->users['cashier2']->id)->exists()) {
            return;
        }
        fake()->seed(2026);
        $this->clock = $clock;
        $today = $clock->today();
        $this->today = $today;
        $this->now = $clock->now();
        $start = $today->subDays(self::HISTORY_DAYS)->max($today->startOfYear());

        $patients = Patient::query()->with('person')->get();
        $this->children = $patients->filter(fn (Patient $p): bool => $p->person->date_of_birth->diffInYears($today) < 16)->values();
        $this->adults = $patients->diff($this->children)->values();
        $this->portal = $patients->filter(fn (Patient $p): bool => str_ends_with((string) $p->person->email, DemoSeeder::EMAIL_DOMAIN))->values();
        $doctors = Doctor::query()->with('specialty')->get();
        $busy = Appointment::query()->get(['provider_id', 'scheduled_date'])
            ->map(fn (Appointment $a): string => $a->provider_id.'|'.$a->scheduled_date->toDateString())->flip();

        $this->stock($inventory, $start);
        $this->treatments($start, $today);

        $previousDrawer = null;
        for ($day = $start; $day->lte($today->addDays(self::FUTURE_DAYS)); $day = $day->addDay()) {
            if (in_array($day->dayOfWeek, [5, 6], true)) {
                continue;
            }
            $visits = [];
            foreach ($doctors as $doctor) {
                if (! $busy->has($doctor->id.'|'.$day->toDateString())) {
                    array_push($visits, ...$this->book($doctor, $day, $today));
                }
            }
            if ($day->gte($today)) {
                continue; // Today and the coming weeks are bookings only.
            }

            foreach ($visits as $visit) {
                $this->care($visit, $prescriptions, $dispensing);
            }
            $previousDrawer = $this->bill($day, $previousDrawer, $invoices, $payments, $refunds, $drawers, $claims);
            $this->spend($day, $expenses);
        }

        // Bank the last drawer, then decide the older insurance claims.
        if ($previousDrawer !== null) {
            $this->at($today, '09:30', 'accountant');
            $this->bank($drawers, $previousDrawer, $today);
        }
        $this->insurance($claims, $today);
        $this->leaves($leaves, $calendar, $start, $today);

        CarbonImmutable::setTestNow();
        Auth::forgetUser();
    }

    /** A large delivery of every medicine at the start of the window, so the history can be dispensed. */
    private function stock(InventoryServiceInterface $inventory, CarbonImmutable $start): void
    {
        $this->at($start, '08:00', 'pharmacist');
        $suppliers = Company::query()->where('type', 'supplier')->pluck('id')->all();
        foreach (Medication::query()->where('is_controlled', false)->get() as $i => $medication) {
            $quantity = in_array($medication->form, [MedicationForm::Inhaler, MedicationForm::Cream], true) ? '120' : '1500';
            $inventory->receive(new ReceiveStockData(
                $medication->id, 'H-'.Str::upper(Str::random(5)), $start->addMonths(18), Qty::of($quantity),
                in_array($medication->form, [MedicationForm::Inhaler, MedicationForm::Cream, MedicationForm::Injection], true) ? '1.100' : '0.060',
                $suppliers[$i % max(1, count($suppliers))] ?? null, false, null,
            ));
        }
    }

    /**
     * The day's bookings of a doctor from their schedule: past days completed (with no-shows and cancellations),
     * today split around the current time, future days confirmed or waiting for confirmation.
     *
     * @return list<Appointment> completed visits of a past day
     */
    private function book(Doctor $doctor, CarbonImmutable $day, CarbonImmutable $today): array
    {
        $slots = $this->slots($doctor->id, $day, $doctor->default_slot_minutes);
        if ($slots === []) {
            return [];
        }
        $past = $day->lt($today);
        $isToday = $day->equalTo($today);
        $wanted = $past || $isToday ? fake()->numberBetween(3, 7) : max(1, 6 - intdiv((int) $today->diffInDays($day), 4) - fake()->numberBetween(0, 2));
        $picked = fake()->randomElements($slots, min($wanted, count($slots)));
        sort($picked);
        $nowTime = $this->clock->toClinic($this->now)->format('H:i:s');
        $specialty = $doctor->specialty->code ?? 'GP';
        [$reasons] = self::CARE[$specialty] ?? self::CARE['GP'];
        $completed = [];

        foreach ($picked as $n => $from) {
            $start = CarbonImmutable::parse($day->toDateString().' '.$from);
            $to = $start->addMinutes($doctor->default_slot_minutes)->format('H:i:s');
            $patient = $this->patientFor($specialty, $day, $from, $to);
            if ($patient === null) {
                continue;
            }
            $roll = fake()->numberBetween(1, 100);
            $status = match (true) {
                $past => $roll <= 84 ? AppointmentStatus::Completed : ($roll <= 92 ? AppointmentStatus::NoShow : AppointmentStatus::Cancelled),
                $isToday && $from < $nowTime => [AppointmentStatus::Completed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress][$n % 3],
                default => $roll <= 80 ? AppointmentStatus::Confirmed : ($roll <= 92 ? AppointmentStatus::Pending : AppointmentStatus::Cancelled),
            };
            $followUp = $n % 4 === 1;
            $atClinic = in_array($status, [AppointmentStatus::Completed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress], true);
            $visitStart = $this->clock->combine($day, substr($from, 0, 5));
            $cancelled = $status === AppointmentStatus::Cancelled;
            $visit = Appointment::query()->create([
                'patient_id' => $patient->id,
                'provider_id' => $doctor->id,
                'type' => $followUp ? AppointmentType::FollowUp : AppointmentType::Consultation,
                'scheduled_date' => $day,
                'start_time' => $from,
                'end_time' => $to,
                'room_id' => $doctor->default_room_id,
                'billable_service_id' => $followUp ? $doctor->follow_up_service_id : $doctor->consultation_service_id,
                'status' => $status,
                'is_attended' => $atClinic,
                'source' => $status === AppointmentStatus::Pending ? AppointmentSource::Portal : fake()->randomElement([AppointmentSource::Phone, AppointmentSource::Phone, AppointmentSource::WalkIn, AppointmentSource::Portal]),
                'reason' => fake()->randomElement($reasons),
                'checked_in_at' => $atClinic ? $visitStart->subMinutes(fake()->numberBetween(3, 15)) : null,
                'started_at' => in_array($status, [AppointmentStatus::Completed, AppointmentStatus::InProgress], true) ? $visitStart->addMinutes(fake()->numberBetween(0, 10)) : null,
                'completed_at' => $status === AppointmentStatus::Completed ? $visitStart->addMinutes($doctor->default_slot_minutes) : null,
                'cancelled_by' => $cancelled ? fake()->randomElement([CancelledBy::Patient, CancelledBy::Patient, CancelledBy::Clinic]) : null,
                'cancellation_reason' => $cancelled ? fake()->randomElement(['Feeling better', 'Travelling', 'Work commitment', 'Doctor on leave', 'Booked by mistake']) : null,
                'cancelled_at' => $cancelled ? $visitStart->subDays(fake()->numberBetween(1, 3)) : null,
            ]);
            if ($past && $status === AppointmentStatus::Completed) {
                $completed[] = $visit;
            }
        }

        return $completed;
    }

    /** @return list<string> start times (HH:MM:SS) the doctor works on that day */
    private function slots(string $providerId, CarbonImmutable $day, int $minutes): array
    {
        $slots = [];
        $blocks = ProviderSchedule::query()->where('provider_id', $providerId)->where('day_of_week', $day->dayOfWeek)->get();
        foreach ($blocks as $block) {
            $end = substr((string) $block->end_time, 0, 5);
            for ($t = CarbonImmutable::parse($day->toDateString().' '.substr((string) $block->start_time, 0, 5)); $t->addMinutes($minutes)->format('H:i') <= $end; $t = $t->addMinutes($minutes)) {
                $slots[] = $t->format('H:i:s');
            }
        }

        return $slots;
    }

    /** A free patient of the right age for the specialty (children for paediatrics, adults elsewhere). */
    private function patientFor(string $specialty, CarbonImmutable $day, string $from, string $to): ?Patient
    {
        // About one visit in twelve belongs to a portal account, so every demo patient has a real history.
        $pool = $specialty === 'PED' ? $this->children : (fake()->boolean(4) && $this->portal->isNotEmpty() ? $this->portal : $this->adults);
        for ($try = 0; $try < 5 && $pool->isNotEmpty(); $try++) {
            $patient = $pool->random();
            if (! Appointment::query()->overlapping($day, $from, $to)->where('patient_id', $patient->id)->exists()) {
                return $patient;
            }
        }

        return null;
    }

    /** A completed visit: signed note with diagnoses (most), a test or scan (some), a prescription dispensed (some). */
    private function care(Appointment $visit, PrescriptionServiceInterface $prescriptions, DispensingServiceInterface $dispensing): void
    {
        $doctor = Doctor::query()->with('specialty')->findOrFail($visit->provider_id);
        $specialty = $doctor->specialty->code ?? 'GP';
        [, $diagnoses, $orders] = self::CARE[$specialty] ?? self::CARE['GP'];
        $end = $visit->completed_at ?? $visit->endsAt();

        if (fake()->boolean(75)) {
            $record = MedicalRecord::query()->create([
                'id' => $visit->id,
                'chief_complaint' => $visit->reason,
                'assessment' => fake()->randomElement(['Stable, improving', 'As expected for the condition', 'Mild, no red flags', 'Needs closer follow-up']),
                'plan' => fake()->randomElement(['Medication as prescribed; review in 2 weeks', 'Lifestyle advice; repeat tests in 3 months', 'Rest and fluids; return if worse', 'Continue current treatment']),
                'temperature_c' => (string) fake()->randomFloat(1, 36.4, 38.6),
                'pulse_bpm' => fake()->numberBetween(62, 104),
                'systolic_bp' => fake()->numberBetween(108, 162),
                'diastolic_bp' => fake()->numberBetween(66, 98),
                'spo2_percent' => fake()->numberBetween(95, 100),
                'weight_kg' => (string) fake()->numberBetween(55, 112),
                'height_cm' => fake()->numberBetween(150, 190),
            ]);
            $codes = Icd10Code::query()->whereIn('code', fake()->randomElements($diagnoses, fake()->numberBetween(1, 2)))->get();
            foreach ($codes as $i => $code) {
                $record->diagnoses()->create(['icd10_code_id' => $code->id, 'is_primary' => $i === 0]);
            }
            $record->forceFill(['signed_by' => $visit->provider_id, 'signed_at' => $end])->save();
        }

        if (fake()->boolean(28)) {
            $this->order($visit, fake()->randomElement($orders), $end);
        }

        $plans = self::RX[$specialty] ?? [];
        if ($plans !== [] && fake()->boolean(35)) {
            $this->at($end, null, null, User::query()->findOrFail($visit->provider_id));
            $lines = array_values(array_map(function (array $line): PrescriptionItemData {
                [$code, $quantity, $dose, $frequency, $days, $route] = $line;

                return new PrescriptionItemData((string) Medication::query()->where('code', $code)->value('id'), $dose, $frequency, MedicationRoute::from($route), $days, $quantity, null);
            }, fake()->randomElements($plans, fake()->numberBetween(1, min(2, count($plans))))));
            try {
                $rx = $prescriptions->create(new PrescriptionData($visit->patient_id, $lines, null, $visit->id, overrideDuplicate: true, overrideAllergy: true));
                if (fake()->boolean(85)) {
                    $this->at($end->addMinutes(20), null, 'pharmacist');
                    $items = array_values($rx->items->map(fn (PrescriptionItem $item): DispenseItemData => new DispenseItemData($item->medication_id, Qty::of($item->quantity), $item->id))->all());
                    $dispensing->dispense(new DispenseData($rx->patient_id, $items, $rx->id));
                }
            } catch (DomainException) {
                // Out of stock or a rule the demo does not need to show.
            }
        }
    }

    /** A test or scan ordered at the visit, done and reported within hours (the last two days still open). */
    private function order(Appointment $visit, string $serviceCode, CarbonImmutable $orderedAt): void
    {
        $service = BillableService::query()->where('code', $serviceCode)->first();
        if ($service === null) {
            return;
        }
        $type = match (true) {
            str_starts_with($serviceCode, 'LAB') => ClinicalOrderType::LabTest,
            str_starts_with($serviceCode, 'XR') => ClinicalOrderType::Xray,
            $serviceCode === 'ECG' => ClinicalOrderType::Ecg,
            default => ClinicalOrderType::Ultrasound,
        };
        $recent = $orderedAt->gt($this->now->subDays(2));
        $abnormal = fake()->boolean(25);
        $room = Room::query()->where('code', $type === ClinicalOrderType::LabTest ? 'LAB-1' : ($type === ClinicalOrderType::Xray ? 'X-1' : 'US-1'))->value('id');
        $done = $orderedAt->addMinutes(fake()->numberBetween(30, 180));
        ClinicalOrder::query()->create([
            'patient_id' => $visit->patient_id,
            'ordered_by' => $visit->provider_id,
            'appointment_id' => $visit->id,
            'type' => $type,
            'billable_service_id' => $service->id,
            'body_part' => match ($serviceCode) {
                'XR-CHEST' => 'Chest PA', 'XR-KNEE' => 'Right knee', 'XR-SPINE' => 'Lumbar spine', 'US-ABD' => 'Abdomen', 'XR-DENT' => 'Teeth (panoramic)', 'ECG' => null, default => 'Blood',
            },
            'priority' => fake()->randomElement([TreatmentPriority::Medium, TreatmentPriority::Medium, TreatmentPriority::High]),
            'status' => $recent ? fake()->randomElement([GenericStatus::Pending, GenericStatus::InProgress]) : GenericStatus::Completed,
            'room_id' => $room,
            'performed_by' => $this->users[fake()->randomElement(['nurse', 'nurse2'])]->id,
            'started_at' => $recent ? null : $orderedAt->addMinutes(15),
            'completed_at' => $recent ? null : $done,
            'reported_by' => $recent ? null : $visit->provider_id,
            'is_abnormal' => $recent ? null : $abnormal,
            'report' => $recent ? null : self::REPORTS[$serviceCode][$abnormal ? 1 : 0],
            'clinical_notes' => $visit->reason,
        ]);
    }

    /**
     * The day at the cash desk: bank yesterday's drawer, charge the day's visits, tests and medicines onto drafts,
     * issue them and take payments (most in full, some in part, some left owing), now and then a refund; close the
     * drawer (rarely short — the admin accepts the difference).
     */
    private function bill(
        CarbonImmutable $day,
        ?CashSession $previous,
        InvoiceServiceInterface $invoices,
        PaymentServiceInterface $payments,
        RefundServiceInterface $refunds,
        CashSessionServiceInterface $drawers,
        InsuranceClaimServiceInterface $claims,
    ): CashSession {
        if ($previous !== null) {
            $this->at($day, '08:30', 'accountant');
            $this->bank($drawers, $previous, $day);
        }

        // The two receptionists and the two cash desks take turns by day.
        $desk = $day->dayOfYear % 2 === 0 ? 'reception2' : 'reception';
        $this->at($day, '20:00', $desk);
        $sources = [
            ...Appointment::query()->whereDate('scheduled_date', $day->toDateString())->where('status', AppointmentStatus::Completed->value)->whereNotNull('billable_service_id')->get()->all(),
            ...ClinicalOrder::query()->where('status', GenericStatus::Completed->value)->whereNotNull('billable_service_id')
                ->whereBetween('completed_at', [$this->clock->combine($day, '00:00'), $this->clock->combine($day, '23:59')])->get()->all(),
        ];
        foreach ($sources as $source) {
            try {
                $invoices->charge($source);
            } catch (AlreadyChargedException|DomainException) {
                // Billed already, or not billable.
            }
        }

        $till = 'cashier2'; // The day cashier's own drawers are seeded by BillingDemoSeeder (one still open today).
        $this->at($day, '20:10', $till);
        $drawer = $drawers->open($this->users[$till], MoneyHelper::of('20.000'));
        $patientIds = collect($sources)->map(fn ($s): string => $s->patient_id)->unique()->all();
        $drafts = Invoice::query()->where('status', InvoiceStatus::Draft->value)->whereIn('patient_id', $patientIds)->get();
        $cashPayment = null;
        foreach ($drafts as $draft) {
            try {
                $invoice = $invoices->issue($draft);
            } catch (DomainException) {
                continue;
            }
            // Insured patients: the clinic claims from the insurer and the patient pays the co-pay.
            $share = $this->claim($claims, $invoice, $day, $till);
            $roll = fake()->numberBetween(1, 100);
            if ($roll > 88) {
                continue; // Left owing.
            }
            $method = $roll <= 42 ? PaymentMethod::Cash : ($roll <= 66 ? PaymentMethod::Card : ($roll <= 78 ? PaymentMethod::BenefitPay : PaymentMethod::BankTransfer));
            $due = $share === null ? $invoice->balance_due : $invoice->balance_due->multipliedBy($share, RoundingMode::DOWN);
            $amount = $roll > 82 ? $due->dividedBy(2, RoundingMode::DOWN) : $due;
            if (! $amount->isPositive()) {
                continue; // Fully covered by the insurer.
            }
            $payment = $payments->record(new RecordPaymentData(
                $invoice->id,
                MoneyHelper::toDb($amount),
                $method,
                $method === PaymentMethod::Cash ? null : Str::upper(Str::random(8)),
                $method === PaymentMethod::Card ? fake()->numerify('####') : null,
            ), (string) Str::uuid());
            if ($method === PaymentMethod::Cash) {
                $cashPayment ??= $payment;
            }
        }

        // About once a fortnight, part of a cash payment is refunded (charged for something not done).
        if ($cashPayment instanceof Payment && fake()->boolean(12)) {
            try {
                $refund = $refunds->request(new RefundRequestData($cashPayment->id, '2.000', null, fake()->randomElement(['Dressing charged but not used', 'Follow-up price applied late', 'Duplicate test charge'])));
                $this->at($day, '20:30', 'accountant');
                $refunds->approve($refund);
                $this->at($day, '20:35', $till);
                $refunds->process($refund, null);
            } catch (DomainException) {
                // Refund rules the demo does not need to show.
            }
        }

        $this->at($day, '21:00', $till);
        $expected = $drawers->report($drawer)->expected;
        $short = fake()->boolean(8);
        $drawer = $drawers->close($drawer, new CloseSessionData(MoneyHelper::toDb($short ? $expected->minus(MoneyHelper::of('1.500')) : $expected), null, $short ? 'Short — change given twice' : 'Exact count'));
        if ($short) {
            $this->at($day, '21:15', 'admin');
            try {
                $drawer = $drawers->approveDifference($drawer, 'Accepted; cashier reminded to count change twice');
            } catch (DomainException) {
                // Already settled.
            }
        }

        return $drawer;
    }

    private function bank(CashSessionServiceInterface $drawers, CashSession $drawer, CarbonImmutable $day): void
    {
        try {
            $drawers->deposit($drawer->refresh(), new DepositData($day, 'NBB-'.Str::upper(Str::random(6)), MoneyHelper::toDb($drawer->counted_closing ?? $drawers->report($drawer)->expected)));
        } catch (DomainException|\Error) {
            // Nothing to bank, or waiting for a supervisor.
        }
    }

    /** Monthly rent and utilities, weekly supplies, now and then maintenance or marketing — approved and paid; the last week still in progress. */
    private function spend(CarbonImmutable $day, ExpenseServiceInterface $expenses): void
    {
        $plans = [];
        if ($day->day <= 2 && $day->dayOfWeek !== 5) {
            $plans[] = [ExpenseCategory::Rent, 'Seef Properties', 'Clinic premises rent, '.$day->format('F'), '1850.000', '0', PaymentMethod::BankTransfer];
            $plans[] = [ExpenseCategory::Utilities, 'Electricity & Water Authority', 'Electricity and water, '.$day->subMonth()->format('F'), (string) fake()->numberBetween(260, 380).'.400', '0', PaymentMethod::BankTransfer];
            $plans[] = [ExpenseCategory::Utilities, 'Batelco', 'Internet and phone lines', '64.000', '6.400', PaymentMethod::BankTransfer];
        }
        if ($day->dayOfWeek === 0) {
            $plans[] = [ExpenseCategory::MedicalSupplies, 'Yiaco Medical Supplies', fake()->randomElement(['Gloves, gauze and syringes', 'Dressing packs and tapes', 'Specimen tubes and swabs']), (string) fake()->numberBetween(70, 260).'.500', '0', PaymentMethod::BankTransfer];
        }
        if ($day->dayOfWeek === 2 && fake()->boolean(45)) {
            $plans[] = fake()->randomElement([
                [ExpenseCategory::OfficeSupplies, 'Ashrafs', 'Printer paper and toner', '38.750', '3.500', PaymentMethod::Cheque],
                [ExpenseCategory::Maintenance, 'CoolAir Services', 'Air-conditioning service', '45.000', '0', PaymentMethod::Cheque],
                [ExpenseCategory::Marketing, 'Gulf Daily News', 'Newspaper advert — new cardiology clinic', '120.000', '12.000', PaymentMethod::BankTransfer],
                [ExpenseCategory::ProfessionalFees, 'KPMG Bahrain', 'Quarterly bookkeeping review', '350.000', '35.000', PaymentMethod::BankTransfer],
                [ExpenseCategory::GovernmentFees, 'NHRA', 'Licence renewal fee', '75.000', '0', PaymentMethod::BankTransfer],
            ]);
        }
        $recent = $day->gt($this->today->subDays(7));
        foreach ($plans as $i => [$category, $payee, $description, $amount, $tax, $method]) {
            $this->at($day, '10:00', 'accountant');
            $expense = $expenses->create(ExpenseData::fromArray([
                'category' => $category->value, 'payee' => $payee, 'description' => $description, 'amount' => $amount, 'tax_amount' => $tax,
                'expense_date' => $day->toDateString(), 'vendor_invoice_number' => strtoupper(substr($payee, 0, 3)).'-'.fake()->numerify('#####'),
            ]));
            if ($recent && $i % 2 === 1) {
                continue; // Draft, still being checked.
            }
            $expenses->submit($expense);
            if ($recent) {
                continue; // Waiting for approval.
            }
            $this->at($day, '12:00', 'admin');
            $expenses->approve($expense);
            $this->at($day, '14:00', 'accountant');
            $expenses->pay($expense, new PayExpenseData($day, $method, 'TRX-'.fake()->numerify('#####')));
        }
    }

    /**
     * Claims the insurer's share of an insured patient's invoice (policy valid, visits with signed diagnoses) and
     * submits it; returns the patient's co-pay share (e.g. "0.2"), or null when nothing was claimed.
     */
    private function claim(InsuranceClaimServiceInterface $claims, Invoice $invoice, CarbonImmutable $day, string $till): ?string
    {
        $patient = Patient::query()->find($invoice->patient_id);
        if ($patient?->insurance_company_id === null || $patient->insurance_expiry_date?->lt($day) || ! fake()->boolean(85)) {
            return null;
        }
        $this->at($day, '20:20', 'accountant');
        try {
            $claim = $claims->createForInvoice($invoice, fake()->boolean(25) ? 'PA-'.fake()->numerify('######') : null);
            if (! $day->gt($this->today->subDays(4)) || fake()->boolean(70)) {
                $claims->submit($claim); // The last few days are partly still being prepared.
            }
        } catch (DomainException) {
            return null; // Not claimable (no signed diagnosis …).
        } finally {
            $this->at($day, '20:25', $till);
        }

        return (string) ((100 - (int) $patient->insurance_coverage_percent) / 100);
    }

    /** The insurer decides claims sent more than two weeks ago: most approved (some in part), a few rejected. */
    private function insurance(InsuranceClaimServiceInterface $claims, CarbonImmutable $today): void
    {
        $this->at($today, '10:00', 'accountant');
        $sent = InsuranceClaim::query()->where('status', 'pending')->where('submitted_at', '<', $today->subDays(14))->get();
        foreach ($sent as $claim) {
            if (fake()->boolean(15)) {
                continue; // The insurer is still reviewing.
            }
            $roll = fake()->numberBetween(1, 100);
            $reference = 'INS-'.fake()->numerify('######');
            try {
                $claims->decide($claim, match (true) {
                    $roll <= 65 => new ClaimDecisionData(true, MoneyHelper::toDb($claim->claimed_amount), $reference, null),
                    $roll <= 85 => new ClaimDecisionData(true, MoneyHelper::toDb($claim->claimed_amount->multipliedBy('0.7', RoundingMode::DOWN)), $reference, null),
                    default => new ClaimDecisionData(false, null, $reference, fake()->randomElement(['Service not covered by the policy', 'Pre-authorisation missing', 'Duplicate claim'])),
                });
            } catch (DomainException) {
                // Decision rules the demo does not need to show.
            }
        }
    }

    /**
     * Treatment plans over the window: finished courses, running ones with sessions booked ahead, one cancelled and
     * a few waiting in the scheduling queue. Who gives each session follows the kind of treatment the doctor ordered:
     * physiotherapy and rehab — the physiotherapist in the gym; wound care, injections, nebulisers — the staff nurse
     * in the treatment room; laser — the dermatologist who ordered it.
     */
    private function treatments(CarbonImmutable $start, CarbonImmutable $today): void
    {
        $doctors = Doctor::query()->with('specialty')->get()->keyBy(fn (Doctor $d): string => $d->specialty->code ?? 'GP');
        $rooms = Room::query()->whereIn('code', ['PT-1', 'TR-1'])->pluck('id', 'code');
        $services = BillableService::query()->whereIn('code', ['PT-SESSION', 'PROC-DRESS', 'PROC-IM', 'PROC-NEB', 'PROC-LASER'])->pluck('id', 'code');
        $nurse = $this->users['nurse']->id;
        $physio = $this->users['nurse2']->id;
        $kinds = [
            [TreatmentType::Physiotherapy, 'Lower back physiotherapy', 'ORTHO', 8, 45],
            [TreatmentType::Physiotherapy, 'Shoulder rehabilitation', 'ORTHO', 10, 45],
            [TreatmentType::Rehabilitation, 'Post-ACL reconstruction rehab', 'ORTHO', 12, 60],
            [TreatmentType::WoundCare, 'Diabetic foot wound care', 'IM', 6, 30],
            [TreatmentType::Injection, 'Vitamin B12 injections', 'IM', 5, 15],
            [TreatmentType::RespiratoryTherapy, 'Nebuliser course', 'PED', 4, 30],
            [TreatmentType::Laser, 'Laser therapy for warts', 'DERM', 4, 30],
            [TreatmentType::Physiotherapy, 'Neck pain physiotherapy', 'GP', 6, 45],
        ];
        $workdays = [];
        for ($d = $start; $d->lte($today->addDays(self::FUTURE_DAYS)); $d = $d->addDay()) {
            if (! in_array($d->dayOfWeek, [5, 6], true)) {
                $workdays[] = $d;
            }
        }
        $todayIndex = collect($workdays)->search(fn (CarbonImmutable $d): bool => $d->gte($today)) ?: count($workdays) - 1;

        for ($i = 0; $i < 16; $i++) {
            [$type, $title, $specialty, $planned, $minutes] = $kinds[$i % count($kinds)];
            $doctor = $doctors->get($specialty) ?? $doctors->first();
            $pool = $specialty === 'PED' ? $this->children : $this->adults;
            if ($doctor === null || $pool->isEmpty()) {
                return;
            }
            // Start somewhere in the window; the last few plans have not started (they wait in the queue).
            $queued = $i >= 13;
            $first = $queued ? null : fake()->numberBetween(0, max(0, $todayIndex - 6));
            // Who, where and what it costs, by kind of treatment.
            [$therapist, $room, $service] = match ($type) {
                TreatmentType::Physiotherapy, TreatmentType::Rehabilitation => [$physio, $rooms['PT-1'] ?? null, $services['PT-SESSION'] ?? null],
                TreatmentType::WoundCare => [$nurse, $rooms['TR-1'] ?? null, $services['PROC-DRESS'] ?? null],
                TreatmentType::Injection => [$nurse, $rooms['TR-1'] ?? null, $services['PROC-IM'] ?? null],
                TreatmentType::RespiratoryTherapy => [$nurse, $rooms['TR-1'] ?? null, $services['PROC-NEB'] ?? null],
                TreatmentType::Laser => [$doctor->id, null, $services['PROC-LASER'] ?? null],
            };
            $plan = Treatment::query()->create([
                'patient_id' => $pool->random()->id, 'doctor_id' => $doctor->id, 'type' => $type, 'title' => $title,
                'priority' => fake()->randomElement([TreatmentPriority::Medium, TreatmentPriority::Medium, TreatmentPriority::High, TreatmentPriority::Urgent]),
                'planned_sessions' => $planned, 'session_duration_minutes' => $minutes, 'billable_service_id' => $service,
                'status' => GenericStatus::Pending, 'start_date' => $first === null ? null : $workdays[$first],
                'diagnosis' => $queued ? null : fake()->randomElement(['Mechanical pain, no neurological deficit', 'Reduced range of movement', 'Chronic wound, slow healing', 'Recurrent wheeze']),
            ]);
            if ($first === null) {
                continue;
            }
            $time = sprintf('%02d:00', 8 + ($i % 5));
            $held = 0;
            $number = 1;
            for ($k = $first; $k < count($workdays) && $number <= $planned; $k += 2) {
                $day = $workdays[$k];
                $from = $this->clock->combine($day, $time);
                $end = $from->addMinutes($minutes);
                if (Appointment::query()->overlapping($day, $time.':00', $end->format('H:i:s'))->where(fn ($q) => $q->where('provider_id', $therapist)->orWhere('patient_id', $plan->patient_id))->exists()) {
                    continue;
                }
                $past = $day->lt($today);
                $status = $past ? (fake()->boolean(88) ? AppointmentStatus::Completed : AppointmentStatus::NoShow) : AppointmentStatus::Confirmed;
                Appointment::query()->create([
                    'patient_id' => $plan->patient_id, 'provider_id' => $therapist, 'type' => AppointmentType::TreatmentSession,
                    'treatment_id' => $plan->id, 'session_number' => $number, 'scheduled_date' => $day,
                    'start_time' => $time.':00', 'end_time' => CarbonImmutable::parse($day->toDateString().' '.$time)->addMinutes($minutes)->format('H:i:s'),
                    'room_id' => $room,
                    'billable_service_id' => $service, 'status' => $status, 'is_attended' => $status === AppointmentStatus::Completed,
                    'source' => AppointmentSource::Phone, 'reason' => $title,
                    'checked_in_at' => $status === AppointmentStatus::Completed ? $from->subMinutes(5) : null,
                    'started_at' => $status === AppointmentStatus::Completed ? $from : null,
                    'completed_at' => $status === AppointmentStatus::Completed ? $end : null,
                ]);
                // A no-show frees its number for the next booking; held and upcoming sessions each keep their own.
                if ($status === AppointmentStatus::Completed) {
                    $held++;
                }
                if ($status !== AppointmentStatus::NoShow) {
                    $number++;
                }
                if (! $past && $number > $planned) {
                    break;
                }
                if (! $past && $k > $todayIndex + 6) {
                    break; // Book about two weeks ahead.
                }
            }
            $plan->forceFill(match (true) {
                $i === 12 => ['status' => GenericStatus::Cancelled, 'cancellation_reason' => 'Patient moved abroad'],
                $held >= $planned => ['status' => GenericStatus::Completed, 'end_date' => $workdays[min($first + 2 * ($planned + 2), $todayIndex)]],
                default => ['status' => GenericStatus::InProgress],
            })->save();
        }
    }

    /**
     * Leave across the team: sick days and annual leave taken in the window (before the attendance HR keeps),
     * requests waiting for HR and approved leave ahead.
     */
    private function leaves(LeaveServiceInterface $leaves, WorkCalendar $calendar, CarbonImmutable $start, CarbonImmutable $today): void
    {
        $type = fn (string $code): string => (string) LeaveType::query()->where('code', $code)->value('id');
        $attendanceFrom = $today->startOfMonth()->subMonthNoOverflow();
        $plans = [
            ['doctor3', 'EMERGENCY', $start->addDays(6), 1, 'Family emergency', 'approve'],
            ['doctor5', 'ANNUAL', $start->addDays(10), 4, 'Conference in Dubai', 'approve'],
            ['nurse2', 'SICK', $start->addDays(3), 0, 'Migraine — sick note to follow', 'approve'],
            ['reception2', 'ANNUAL', $today->addDays(9), 2, 'Family wedding', null],
            ['doctor4', 'ANNUAL', $today->addDays(16), 4, 'Holiday in Turkey', 'approve'],
            ['cashier2', 'ANNUAL', $today->addDays(12), 1, 'Moving house', null],
            ['doctor6', 'ANNUAL', $today->addDays(30), 9, 'Hajj', 'approve'],
            ['doctor', 'ANNUAL', $today->addDays(2), 0, 'Personal errand', 'reject'],
        ];
        foreach ($plans as [$who, $code, $from, $extra, $reason, $decision]) {
            $employee = Employee::query()->whereHas('person', fn ($q) => $q->where('email', $who.DemoSeeder::EMAIL_DOMAIN))->first();
            if ($employee === null || ($from->lt($today) && $from->gte($attendanceFrom))) {
                continue;
            }
            $days = $calendar->workingDays($employee, $from, $from->addWeeks(4));
            if (count($days) <= $extra) {
                continue;
            }
            try {
                // Leave already taken is recorded by HR; leave ahead is asked for by the employee.
                $past = $from->lt($today);
                $this->at($past ? $from : $today, '09:00', $past ? 'hr' : null, $past ? null : User::query()->find($employee->id));
                $request = $leaves->request($employee, new LeaveRequestData($type($code), $days[0], $days[$extra], reason: $reason));
                Auth::setUser($this->users['hr']);
                match ($decision) {
                    'approve' => $leaves->approve($request, 'Approved'),
                    'reject' => $leaves->reject($request, 'Clinic fully booked that day — please move the appointment'),
                    default => null,
                };
            } catch (DomainException|ValidationException) {
                // Not enough balance, too little notice or overlapping leave: leave it out.
            }
        }
    }

    /** Moves the clock to a clinic time of a day and signs in as a demo user (or the given one). */
    private function at(CarbonImmutable $when, ?string $time, ?string $as, ?User $user = null): void
    {
        CarbonImmutable::setTestNow($time === null ? $when : $this->clock->combine($when, $time));
        $who = $user ?? ($as !== null ? $this->users[$as] : null);
        if ($who !== null) {
            Auth::setUser($who);
        }
    }
}
