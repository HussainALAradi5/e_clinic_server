<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Pharmacy\MedicationRoute;
use App\Enums\Reference\CompanyType;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Pharmacy\Dispensing\Contracts\DispensingServiceInterface;
use App\Modules\Pharmacy\Dispensing\Data\AdministerData;
use App\Modules\Pharmacy\Dispensing\Data\DispenseData;
use App\Modules\Pharmacy\Dispensing\Data\DispenseItemData;
use App\Modules\Pharmacy\Inventory\Contracts\InventoryServiceInterface;
use App\Modules\Pharmacy\Inventory\Data\ReceiveStockData;
use App\Modules\Pharmacy\Inventory\Data\StockAdjustmentData;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Medications\Contracts\MedicationServiceInterface;
use App\Modules\Pharmacy\Medications\Data\MedicationData;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Prescriptions\Contracts\PrescriptionServiceInterface;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionData;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionItemData;
use App\Modules\Pharmacy\Prescriptions\Models\PrescriptionItem;
use App\Modules\Pharmacy\Support\Qty;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo pharmacy (Phase 5), through the real services: a catalogue of common medicines (one controlled), deliveries
 * from the suppliers (some booked as purchase expenses), a lot expiring soon, one quarantined, one already expired and
 * written off, a medicine below its reorder level; prescriptions from the visits — dispensed in full, in part, still in
 * the queue and one cancelled; an over-the-counter sale, a count correction and, when a session is running, an
 * injection given during it.
 */
final class PharmacyDemoSeeder extends Seeder
{
    /** code => [generic, brand, form, strength, unit, price, requires_prescription, controlled, reorder, manufacturer] */
    private const MEDICATIONS = [
        'MED-AMOX-500' => ['Amoxicillin', 'Amoxil', 'capsule', '500 mg', 'capsule', '0.250', true, false, 60, 'Julphar Gulf Pharmaceutical'],
        'MED-AZIT-250' => ['Azithromycin', 'Zithromax', 'tablet', '250 mg', 'tablet', '0.900', true, false, 30, 'Pfizer Inc.'],
        'MED-PARA-500' => ['Paracetamol', 'Panadol', 'tablet', '500 mg', 'tablet', '0.050', false, false, 100, 'Julphar Gulf Pharmaceutical'],
        'MED-IBU-400' => ['Ibuprofen', 'Brufen', 'tablet', '400 mg', 'tablet', '0.080', false, false, 100, null],
        'MED-OMEP-20' => ['Omeprazole', 'Losec', 'capsule', '20 mg', 'capsule', '0.300', true, false, 40, 'Novartis AG'],
        'MED-SALB-100' => ['Salbutamol', 'Ventolin', 'inhaler', '100 mcg/dose', 'piece', '3.200', true, false, 10, null],
        'MED-CEFT-1G' => ['Ceftriaxone', 'Rocephin', 'injection', '1 g', 'vial', '3.500', true, false, 10, 'Novartis AG'],
        'MED-DICL-75' => ['Diclofenac', 'Voltaren', 'injection', '75 mg/3 ml', 'ampoule', '1.100', true, false, 10, 'Novartis AG'],
        'MED-FUSI-2' => ['Fusidic acid', 'Fucidin', 'cream', '2%', 'tube', '2.400', true, false, 5, null],
        'MED-MORPH-10' => ['Morphine', null, 'injection', '10 mg/ml', 'ampoule', '1.800', true, true, 5, 'Pfizer Inc.'],
        'MED-ORS' => ['Oral rehydration salts', 'ORS', 'other', '20.5 g', 'pack', '0.400', false, false, 20, null],
    ];

    public function run(
        MedicationServiceInterface $medications,
        InventoryServiceInterface $inventory,
        PrescriptionServiceInterface $prescriptions,
        DispensingServiceInterface $dispensing,
        ClinicClock $clock,
    ): void {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Medication::query()->withDeleted()->exists()) {
            return;
        }
        $pharmacist = $this->user('pharmacist');
        $colleague = $this->user('admin');
        $doctor = $this->user('doctor');
        $nurse = $this->user('nurse');
        if ($pharmacist === null || $doctor === null || $nurse === null || $colleague === null) {
            return;
        }
        $this->call(FiscalYearSeeder::class);
        $today = $clock->today();
        $company = fn (?string $name): ?string => $name === null ? null : Company::query()->where('name', $name)->value('id');
        $suppliers = Company::query()->where('type', CompanyType::Supplier->value)->orderBy('name')->pluck('id')->all();

        Auth::setUser($pharmacist);
        foreach (self::MEDICATIONS as $code => [$generic, $brand, $form, $strength, $unit, $price, $rx, $controlled, $reorder, $maker]) {
            $medications->create(MedicationData::fromArray([
                'code' => $code, 'generic_name' => $generic, 'brand_name' => $brand, 'form' => $form, 'strength' => $strength, 'unit' => $unit,
                'price' => $price, 'tax_rate' => '0', 'requires_prescription' => $rx, 'is_controlled' => $controlled, 'reorder_level' => $reorder,
                'manufacturer_id' => $company($maker), 'storage_notes' => $form === 'injection' ? 'Store below 25 °C, protect from light' : null,
            ]));
        }

        $med = fn (string $code): Medication => Medication::query()->where('code', $code)->firstOrFail();
        $lot = fn (string $number): MedicationBatch => MedicationBatch::query()->where('batch_number', $number)->firstOrFail();

        // An expired lot from four months ago, written off by the nightly job.
        CarbonImmutable::setTestNow($today->subMonths(4)->setTime(10, 0)->utc());
        $this->receive($inventory, $med('MED-AMOX-500'), 'AMX-2302', $today->subWeek(), '40', '0.110', $suppliers[0] ?? null);
        CarbonImmutable::setTestNow();
        $inventory->writeOffExpired($today);

        // This month's deliveries (the first two booked as purchases for finance to approve and pay).
        foreach ([
            ['MED-AMOX-500', 'AMX-2411', 14, '200', '0.120', true],
            ['MED-AMOX-500', 'AMX-2407', 2, '30', '0.110', false],
            ['MED-AZIT-250', 'AZ-7781', 18, '60', '0.450', true],
            ['MED-PARA-500', 'PAR-5521', 24, '500', '0.015', false],
            ['MED-IBU-400', 'IBU-1180', 20, '300', '0.025', false],
            ['MED-IBU-400', 'IBU-1176', 16, '100', '0.025', false],
            ['MED-OMEP-20', 'OMP-3310', 12, '120', '0.090', false],
            ['MED-SALB-100', 'VEN-0921', 22, '6', '1.600', false],
            ['MED-CEFT-1G', 'CFT-4471', 15, '40', '1.300', false],
            ['MED-DICL-75', 'DCL-2290', 13, '50', '0.350', false],
            ['MED-FUSI-2', 'FUC-6602', 10, '25', '1.050', false],
            ['MED-MORPH-10', 'MOR-0112', 11, '20', '0.700', false],
            ['MED-ORS', 'ORS-3301', 1, '60', '0.120', false],
        ] as $i => [$code, $number, $months, $quantity, $cost, $expense]) {
            $this->receive($inventory, $med($code), $number, $today->addMonths($months), $quantity, $cost, $suppliers[$i % max(1, count($suppliers))] ?? null, $expense);
        }
        $inventory->setQuarantine($lot('IBU-1176'), true, 'Supplier recall notice GDD-RC-118');
        $inventory->adjust($lot('PAR-5521'), new StockAdjustmentData(Qty::of('-4'), 'Strips damaged on the shelf'));

        // Prescriptions from today's and recent visits.
        $visits = Appointment::query()->where('provider_id', $doctor->id)
            ->whereIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::InProgress->value])
            ->orderByDesc('scheduled_date')->limit(4)->get();
        Auth::setUser($doctor);
        $line = fn (string $code, string $quantity, string $dose = '1 tablet', string $frequency = '3 times daily', int $days = 7, string $route = 'oral', ?string $note = 'After meals') => new PrescriptionItemData($med($code)->id, $dose, $frequency, MedicationRoute::from($route), $days, $quantity, $note);
        $plans = [
            [$line('MED-AMOX-500', '21', '1 capsule'), $line('MED-PARA-500', '20', '1–2 tablets', 'Every 6 hours as needed', 5, 'oral', 'Max 8 tablets a day')],
            [$line('MED-AZIT-250', '6', '2 tablets on day 1, then 1', 'Once daily', 5), $line('MED-OMEP-20', '28', '1 capsule', 'Once daily before breakfast', 28, 'oral', null)],
            [$line('MED-SALB-100', '1', '2 puffs', 'When short of breath', 30, 'inhalation', 'Shake well before use')],
            [$line('MED-FUSI-2', '1', 'Thin layer', 'Twice daily', 7, 'topical', 'Affected skin only')],
        ];
        $written = [];
        foreach ($visits as $i => $visit) {
            if (! isset($plans[$i])) {
                break;
            }
            $written[] = $prescriptions->create(new PrescriptionData($visit->patient_id, $plans[$i], null, $visit->id, overrideDuplicate: true, overrideAllergy: true));
        }

        // The portal patient's pain relief for the knee, waiting at the pharmacy (My prescriptions, with its QR code).
        $portal = Patient::query()->whereHas('person', fn ($q) => $q->where('email', 'patient@demo.eclinic.bh'))->value('id');
        if (is_string($portal)) {
            $prescriptions->create(new PrescriptionData($portal, [$line('MED-PARA-500', '20', '1–2 tablets', 'Every 6 hours as needed', 10, 'oral', 'For knee pain; at most 8 tablets a day')], null, null, overrideDuplicate: true, overrideAllergy: true));
        }

        Auth::setUser($pharmacist);
        if (isset($written[0])) {
            // In full.
            $rx = $written[0];
            $items = array_values($rx->items->map(fn (PrescriptionItem $item): DispenseItemData => new DispenseItemData($item->medication_id, Qty::of($item->quantity), $item->id))->all());
            $dispensing->dispense(new DispenseData($rx->patient_id, $items, $rx->id, null, 'Finish the whole antibiotic course.'));
        }
        if (isset($written[1])) {
            // In part: the azithromycin now, the omeprazole when it is collected.
            $rx = $written[1];
            $first = $rx->items->firstOrFail();
            $dispensing->dispense(new DispenseData($rx->patient_id, [new DispenseItemData($first->medication_id, Qty::of($first->quantity), $first->id)], $rx->id));
        }
        if (isset($written[3])) {
            Auth::setUser($doctor);
            $prescriptions->cancel($written[3], 'Changed to an oral treatment after review');
            Auth::setUser($pharmacist);
        }
        if (isset($written[0])) {
            $dispensing->dispense(new DispenseData($written[0]->patient_id, [new DispenseItemData($med('MED-ORS')->id, Qty::of('2'))], null, null, 'Over the counter'));
        }

        // An injection given during a running session.
        $session = Appointment::query()->whereIn('status', [AppointmentStatus::InProgress->value, AppointmentStatus::CheckedIn->value])->orderBy('start_time')->first();
        if ($session !== null) {
            Auth::setUser($nurse);
            $dispensing->administer($session, new AdministerData([new DispenseItemData($med('MED-DICL-75')->id, Qty::of('1'), null, null, MedicationRoute::Intramuscular, '75 mg')], null, 'Right gluteal, no reaction after 15 minutes'));
        }

        Auth::forgetUser();
    }

    private function receive(InventoryServiceInterface $inventory, Medication $medication, string $lot, CarbonImmutable $expiry, string $quantity, string $cost, ?string $supplier, bool $expense = false): MedicationBatch
    {
        return $inventory->receive(new ReceiveStockData($medication->id, $lot, $expiry, Qty::of($quantity), $cost, $supplier, $expense, $expense ? 'INV-'.$lot : null));
    }

    private function user(string $name): ?User
    {
        return User::query()->whereHas('person', fn ($q) => $q->where('email', $name.DemoSeeder::EMAIL_DOMAIN))->first();
    }
}
