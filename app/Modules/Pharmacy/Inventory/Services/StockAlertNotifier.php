<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Services;

use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\Recipients;
use Illuminate\Support\Facades\Config;

/**
 * **Scheduled daily** — tells the staff who receive stock how many medicines are at or below their reorder level and
 * how many lots expire soon (nothing is sent when all is well). Returns how many people were told.
 */
final class StockAlertNotifier
{
    public function __construct(private readonly NotificationServiceInterface $notifications, private readonly ClinicClock $clock) {}

    public function __invoke(): int
    {
        $today = $this->clock->today();
        $days = Config::integer('pharmacy.near_expiry_days');

        $stocked = Medication::query()->withStock($today)->where('medications.reorder_level', '>', 0)->where('medications.is_archived', false);
        $low = Medication::query()->fromSub($stocked, 'medications')->whereColumn('stock_on_hand', '<=', 'reorder_level')->count();
        $expiring = MedicationBatch::query()->dispensable($today)->where('qty_on_hand', '>', 0)
            ->whereDate('expiry_date', '<=', $today->addDays($days)->toDateString())->count();
        if ($low === 0 && $expiring === 0) {
            return 0;
        }

        $people = Recipients::withPermission('inventory.receive');
        $people->each(fn (Person $p) => $this->notifications->send('pharmacy.stock_alert', $p, null, ['low' => $low, 'expiring' => $expiring, 'days' => $days]));

        return $people->count();
    }
}
