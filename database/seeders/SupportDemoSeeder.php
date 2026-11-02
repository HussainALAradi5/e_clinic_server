<?php

namespace Database\Seeders;

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Communication\TicketCategory;
use App\Enums\Communication\TicketConfirmation;
use App\Enums\Shared\GenericStatus;
use App\Models\Communication\SupportTicket;
use App\Models\Identity\Person;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Database\Seeder;

/**
 * Demo support tickets from different staff over the last three weeks, in every status, priority and category,
 * some taken and solved by the admin, with replies — enough for two pages in the list. Never in production.
 */
final class SupportDemoSeeder extends Seeder
{
    /** [requester, subject, category, priority, status, days ago, replies [[who, text]], resolution] */
    private const TICKETS = [
        ['nurse', 'Label printer in ward 2 is offline', 'equipment', 'high', 'in_progress', 0, [['admin', 'On my way with a new cartridge.']], null],
        ['reception', 'Cannot print the day list from the front desk', 'technical', 'urgent', 'pending', 0, [], null],
        ['doctor', 'Need access to the physiotherapy plans', 'access', 'medium', 'completed', 1, [['admin', 'Added the physio plans to your role.'], ['doctor', 'Works now, thanks!']], 'Gave the doctor role read access to physiotherapy plans.'],
        ['pharmacist', 'Barcode scanner beeps but does not read', 'equipment', 'high', 'completed', 2, [['admin', 'Replaced the USB cable.']], 'Faulty cable replaced; scanner tested on three packs.'],
        ['cashier', 'Receipt shows the wrong VAT number', 'data', 'urgent', 'in_progress', 1, [['admin', 'Checking the company settings now.']], null],
        ['hr', 'Attendance shows a nurse absent who was on leave', 'data', 'medium', 'pending', 1, [], null],
        ['accountant', 'Export of the trial balance is slow', 'technical', 'medium', 'pending', 2, [], null],
        ['doctor2', 'Second screen in room 3 flickers', 'equipment', 'medium', 'cancelled', 6, [['admin', 'Room 3 screen is being replaced by the supplier; tracking it there.']], null],
        ['reception2', 'Forgot my two-step sign-in phone at home', 'access', 'high', 'completed', 3, [], 'Signed in with a recovery code; asked to set up the app again.'],
        ['nurse2', 'Wheelchair 2 has a flat tyre', 'equipment', 'medium', 'pending', 3, [], null],
        ['doctor3', 'ICD-10 search does not find "lumbago"', 'technical', 'medium', 'completed', 4, [['admin', 'Synonym added to the search list.']], 'Added lumbago as a search term for M54.5.'],
        ['cashier2', 'Cash drawer report missing yesterday', 'data', 'high', 'in_progress', 2, [], null],
        ['doctor4', 'Prescription template has an old clinic phone', 'data', 'medium', 'completed', 5, [], 'Clinic phone updated in settings; templates use it now.'],
        ['pharmacist', 'Fridge temperature alarm keeps going off', 'equipment', 'urgent', 'completed', 5, [['admin', 'Technician came; door seal changed.']], 'Door seal replaced; alarm quiet for 24 h.'],
        ['reception', 'Wi-Fi drops in the waiting room', 'technical', 'medium', 'pending', 6, [], null],
        ['doctor5', 'Request: add a sports medicine specialty', 'other', 'medium', 'pending', 7, [], null],
        ['hr', 'Payslip PDF cuts off the last line', 'technical', 'medium', 'completed', 8, [], 'Margin fixed in the payslip print.'],
        ['nurse', 'Need a second login for the night shift trainee', 'access', 'medium', 'cancelled', 9, [['admin', 'Trainees use their own account — HR creates it.']], null],
        ['accountant', 'Bank charges account missing in the chart', 'data', 'medium', 'completed', 10, [], 'Account 6150 Bank charges added.'],
        ['doctor6', 'Dictation microphone not detected', 'equipment', 'medium', 'in_progress', 4, [], null],
        ['cashier', 'Card machine slow to connect', 'equipment', 'high', 'completed', 11, [], 'Card machine moved to the wired network.'],
        ['reception2', 'Patient merged twice by mistake', 'data', 'urgent', 'completed', 12, [['admin', 'Restored from the audit history.']], 'Duplicate merge undone from the audit history.'],
        ['pharmacist', 'Low stock email arrives twice', 'technical', 'medium', 'pending', 13, [], null],
        ['doctor', 'Calendar colours hard to read in dark mode', 'other', 'medium', 'completed', 14, [], 'Calendar colours adjusted for dark mode.'],
        ['nurse2', 'Cannot attach a photo from the tablet', 'technical', 'medium', 'pending', 15, [], null],
        ['hr', 'Leave balance wrong after carry-over', 'data', 'high', 'completed', 16, [], 'Carry-over recalculated for 2026.'],
        ['accountant', 'Need read access to payroll runs', 'access', 'medium', 'cancelled', 18, [], null],
        ['reception', 'Queue screen frozen at the entrance', 'equipment', 'urgent', 'completed', 20, [['admin', 'Restarted and updated the screen.']], 'Screen restarted and set to reload nightly.'],
    ];

    public function run(ClinicClock $clock): void
    {
        if ((app()->isProduction() && ! config('app.seed_demo')) || SupportTicket::query()->exists()) {
            return;
        }
        $ids = Person::query()
            ->where('email', 'like', '%'.DemoSeeder::EMAIL_DOMAIN)
            ->pluck('id', 'email')
            ->mapWithKeys(fn (string $id, string $email): array => [str_replace(DemoSeeder::EMAIL_DOMAIN, '', $email) => $id])
            ->all();
        $admin = $ids['admin'] ?? null;
        if ($admin === null) {
            return;
        }

        foreach (self::TICKETS as $i => [$who, $subject, $category, $priority, $status, $daysAgo, $replies, $resolution]) {
            if (! isset($ids[$who])) {
                continue;
            }
            $opened = $clock->today()->subDays($daysAgo)->setTime(8 + $i % 9, ($i * 7) % 60)->utc();
            $status = GenericStatus::from($status);
            $ticket = new SupportTicket([
                'requester_id' => $ids[$who],
                'subject' => $subject,
                'description' => $subject.'. Noticed this morning; it happens every time. Please have a look when you can.',
                'category' => TicketCategory::from($category),
                'priority' => TreatmentPriority::from($priority),
            ]);
            $ticket->forceFill([
                'status' => $status,
                'assigned_to' => $status === GenericStatus::Pending ? null : $admin,
                'resolved_by' => $status === GenericStatus::Completed ? $admin : null,
                'resolved_at' => $status === GenericStatus::Completed ? $opened->addHours(3) : null,
                'resolution' => $resolution,
                // The steps (split from the demo text); recent fixes wait for the requester, older ones are confirmed.
                'resolution_steps' => $resolution === null ? null : array_values(array_filter(array_map('trim', preg_split('/[;.]\s+/', rtrim($resolution, '.')) ?: []))),
                'confirmation' => $status === GenericStatus::Completed ? ($daysAgo <= 3 ? TicketConfirmation::Awaiting : TicketConfirmation::Confirmed) : null,
                'confirmed_at' => $status === GenericStatus::Completed && $daysAgo > 3 ? $opened->addHours(6) : null,
                'created_at' => $opened,
                'updated_at' => $opened,
            ])->save();
            foreach ($replies as $n => [$author, $text]) {
                $at = $opened->addMinutes(40 * ($n + 1));
                $ticket->comments()->make(['author_id' => $ids[$author] ?? $admin, 'body' => $text])
                    ->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            }
        }
    }
}
