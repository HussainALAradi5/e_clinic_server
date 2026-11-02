<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\ShiftType;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use Illuminate\Database\Seeder;

/**
 * Demo shifts: a fixed morning and evening shift and a flexible office shift, with the demo staff on them (the
 * doctors and nurses in the morning, the second receptionist and cashier in the evening, back office flexible).
 * A few people are left without a shift so the board has something to place.
 */
final class ShiftDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::query()->whereNotNull('shift_id')->exists()) {
            return;
        }
        Shift::query()->delete();
        $make = fn (string $name, ShiftType $type, string $from, string $to, ?string $latest = null, ?int $minutes = null, ?string $notes = null): Shift => Shift::query()->create([
            'name' => $name, 'type' => $type, 'start_time' => $from, 'end_time' => $to, 'latest_start' => $latest,
            'required_minutes' => $minutes ?? ((int) substr($to, 0, 2) - (int) substr($from, 0, 2)) * 60, 'notes' => $notes,
        ]);
        $morning = $make('Morning', ShiftType::Fixed, '08:00', '16:00', notes: 'Clinic floor: doctors, nurses, front desk.');
        $evening = $make('Evening', ShiftType::Fixed, '13:00', '21:00', notes: 'Late clinics and the evening front desk.');
        $office = $make('Office flexi', ShiftType::Flexible, '07:00', '19:00', '10:00', 480, 'Back office: in by 10:00, eight hours a day.');

        $byEmail = fn (array $emails): array => Employee::query()->join('persons', 'persons.id', '=', 'employees.id')
            ->whereIn('persons.email', $emails)->where('employees.status', '!=', EmployeeStatus::Terminated)->pluck('employees.id')->all();
        $at = fn (string $who): string => "{$who}@demo.eclinic.bh";

        // Directly (no signed-in user while seeding); the hours follow the shift as ShiftService::assign does.
        $assign = fn (Shift $shift, array $ids) => Employee::query()->whereIn('id', $ids)->update([
            'shift_id' => $shift->id, 'work_start_time' => $shift->arriveBy(), 'work_end_time' => $shift->expectedEnd(),
        ]);
        $assign($morning, $byEmail(array_map($at, ['doctor', 'doctor2', 'doctor3', 'doctor5', 'nurse', 'nurse2', 'reception', 'pharmacist', 'cashier'])));
        $assign($evening, $byEmail(array_map($at, ['doctor4', 'doctor7', 'reception2', 'cashier2'])));
        $assign($office, $byEmail(array_map($at, ['hr', 'accountant', 'support'])));
    }
}
