<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\InvoiceStatus;
use App\Enums\Payments\PatientPackageStatus;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Payments\PackageCoverServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Doctor;
use App\Models\Payments\Invoice;
use App\Models\Payments\PackageSession;
use App\Models\Payments\PatientPackage;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Support\Facades\DB;

final class PackageCoverService implements PackageCoverServiceInterface
{
    public function __construct(
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {}

    public function coverVisit(Appointment $appointment): ?PackageSession
    {
        if ($appointment->billable_service_id === null) {
            return null;
        }

        return DB::transaction(function () use ($appointment): ?PackageSession {
            $existing = PackageSession::query()->where('appointment_id', $appointment->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            $day = $appointment->scheduled_date->toDateString();
            // The package ending soonest is used first.
            $package = PatientPackage::query()
                ->where('patient_id', $appointment->patient_id)
                ->where('covered_service_id', $appointment->billable_service_id)
                ->where('status', PatientPackageStatus::Active->value)
                ->whereColumn('sessions_used', '<', 'sessions_total')
                ->whereDate('starts_on', '<=', $day)
                ->whereDate('expires_on', '>=', $day)
                ->orderBy('expires_on')
                ->lockForUpdate()
                ->first();
            if ($package === null) {
                return null;
            }
            $value = $package->sessionValue($package->sessions_used + 1);
            $session = $package->sessionsTaken()->create(['appointment_id' => $appointment->id, 'value' => $value]);
            $doctor = Doctor::query()->whereKey($appointment->provider_id)->exists() ? $appointment->provider_id : null;
            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $session,
                $this->clock->today(),
                __('accounting.package_session', ['package' => $package->code, 'visit' => $appointment->code]),
                [
                    JournalLineData::debit(AccountMappingKey::UnearnedPackages, $value, null, $package->patient_id),
                    JournalLineData::credit($package->coveredService->revenue_account_id, $value, null, $package->patient_id, $doctor),
                ],
            ));
            $session->update(['journal_entry_id' => $entry->id]);
            $package->sessions_used++;
            if ($package->sessionsLeft() === 0) {
                $package->status = PatientPackageStatus::UsedUp;
            }
            $package->save();

            return $session;
        });
    }

    public function activatePaid(Invoice $invoice): int
    {
        if ($invoice->status !== InvoiceStatus::Paid) {
            return 0;
        }
        $today = $this->clock->today();
        $activated = 0;
        PatientPackage::query()->with('package')
            ->where('invoice_id', $invoice->id)
            ->where('status', PatientPackageStatus::AwaitingPayment->value)
            ->get()
            ->each(function (PatientPackage $p) use ($today, &$activated): void {
                $p->update([
                    'status' => PatientPackageStatus::Active,
                    'starts_on' => $today,
                    'expires_on' => $today->addDays($p->package->validity_days),
                ]);
                $activated++;
            });

        return $activated;
    }

    public function cancelUnpaid(Invoice $invoice): int
    {
        return PatientPackage::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PatientPackageStatus::AwaitingPayment->value)
            ->update(['status' => PatientPackageStatus::Cancelled->value]);
    }

    public function expireDue(): int
    {
        $expired = 0;
        PatientPackage::query()
            ->where('status', PatientPackageStatus::Active->value)
            ->whereDate('expires_on', '<', $this->clock->today()->toDateString())
            ->get()
            ->each(function (PatientPackage $package) use (&$expired): void {
                DB::transaction(function () use ($package): void {
                    $forfeit = $package->remainingValue();
                    if ($forfeit->isPositive()) {
                        $this->journal->postAutomatic(new AutomaticEntryData(
                            $package,
                            $this->clock->today(),
                            __('accounting.package_forfeit', ['package' => $package->code]),
                            [
                                JournalLineData::debit(AccountMappingKey::UnearnedPackages, $forfeit, null, $package->patient_id),
                                JournalLineData::credit(AccountMappingKey::FeeRevenue, $forfeit, null, $package->patient_id),
                            ],
                        ));
                    }
                    $package->update(['status' => PatientPackageStatus::Expired]);
                });
                $expired++;
            });

        return $expired;
    }
}
